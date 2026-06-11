<?php
// proxy.php - MYSORE RACE CLUB - VERSION 1.1
// Lambda URL → CloudFront keys → stream from cfs.mysoreraceclub.com

// --- CONFIGURATION ---
$refresh_token  = "6c5344beab1973c17cfeaa7896ed7c4980dc386e"; // Update when expired
$lambda_url     = "https://2cocpjs3ld7nwyijvy43bkx6ze0wczol.lambda-url.ap-south-1.on.aws/";
$fallback_url   = "https://cfs.mysoreraceclub.com/dc416bc80b38b882e5d5af6ede416e8d/index.m3u8";
$cache_file     = sys_get_temp_dir() . '/mysore_key_cache_v1.json';
$cache_duration = 20; // 20s — keys rotate every ~25s
// --- END CONFIGURATION ---

// Allow cross-origin requests (needed for iframe embedding)
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, OPTIONS");
header("Access-Control-Allow-Headers: *");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// Disable PHP output buffering for pipeline streaming
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', 1);
@ini_set('zlib.output_compression', 0);
@ini_set('implicit_flush', 1);
for ($i = 0; $i < ob_get_level(); $i++) { ob_end_flush(); }
ob_implicit_flush(1);

// --- STEP 1: GET CLOUDFRONT KEYS (cached) ---

$cf_key_pair_id = $cf_policy = $cf_signature = "";
$keys_loaded = false;

// Try cache first
if (file_exists($cache_file) && (time() - filemtime($cache_file) < $cache_duration)) {
    $cached = json_decode(file_get_contents($cache_file), true);
    if (!empty($cached['id'])) {
        $cf_key_pair_id = $cached['id'];
        $cf_policy      = $cached['policy'];
        $cf_signature   = $cached['sig'];
        $keys_loaded    = true;
    }
}

// Fetch fresh keys from Lambda
if (!$keys_loaded) {
    $ch_api = curl_init($lambda_url);
    curl_setopt_array($ch_api, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER         => true,
        CURLOPT_TIMEOUT        => 8,
        CURLOPT_SSL_VERIFYHOST => 0,
        CURLOPT_SSL_VERIFYPEER => 0,
        CURLOPT_HTTPHEADER     => [
            "Origin: https://api.mysoreraceclub.com",
            "Referer: https://api.mysoreraceclub.com/",
            "Cookie: refreshToken=" . $refresh_token,
            "User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36",
            "Accept: */*"
        ]
    ]);

    $raw       = curl_exec($ch_api);
    $hdr_size  = curl_getinfo($ch_api, CURLINFO_HEADER_SIZE);
    $http_code = curl_getinfo($ch_api, CURLINFO_HTTP_CODE);
    curl_close($ch_api);

    if ($raw && $http_code === 200) {
        // Method 1: Parse JSON body (Lambda returns keys in data object)
        $body = substr($raw, $hdr_size);
        $json = json_decode($body, true);

        if (isset($json['data']['CloudFront-Key-Pair-Id'])) {
            $cf_key_pair_id = $json['data']['CloudFront-Key-Pair-Id'];
            $cf_policy      = $json['data']['CloudFront-Policy'];
            $cf_signature   = $json['data']['CloudFront-Signature'];
            $keys_loaded    = true;
        }

        // Method 2: Fallback — parse set-cookie headers
        if (!$keys_loaded) {
            $headers = substr($raw, 0, $hdr_size);
            preg_match('/set-cookie:\s*CloudFront-Key-Pair-Id=([^;]+)/i', $headers, $m1);
            preg_match('/set-cookie:\s*CloudFront-Policy=([^;]+)/i',      $headers, $m2);
            preg_match('/set-cookie:\s*CloudFront-Signature=([^;]+)/i',   $headers, $m3);

            if (!empty($m1[1]) && !empty($m2[1]) && !empty($m3[1])) {
                $cf_key_pair_id = trim($m1[1]);
                $cf_policy      = trim($m2[1]);
                $cf_signature   = trim($m3[1]);
                $keys_loaded    = true;
            }
        }

        if ($keys_loaded) {
            file_put_contents($cache_file, json_encode([
                'id'     => $cf_key_pair_id,
                'policy' => $cf_policy,
                'sig'    => $cf_signature
            ]));
        }
    }
}

// Safety check
if (empty($cf_key_pair_id)) {
    http_response_code(503);
    exit("Error: Unable to fetch CloudFront keys. refreshToken may have expired — update line 5 in proxy.php.");
}

// --- STEP 2: GET STREAM URL ---

$target_url = isset($_GET['url']) ? $_GET['url'] : $fallback_url;

// --- STEP 3: PROXY THE STREAM ---

$is_m3u8       = (strpos($target_url, '.m3u8') !== false);
$cookie_string = "CloudFront-Key-Pair-Id=$cf_key_pair_id; CloudFront-Policy=$cf_policy; CloudFront-Signature=$cf_signature";

$ch = curl_init();
curl_setopt_array($ch, [
    CURLOPT_URL            => $target_url,
    CURLOPT_FOLLOWLOCATION => true,
    CURLOPT_COOKIE         => $cookie_string,
    CURLOPT_USERAGENT      => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/148.0.0.0 Safari/537.36',
    CURLOPT_CONNECTTIMEOUT => 5,
    CURLOPT_TIMEOUT        => 30,
    CURLOPT_SSL_VERIFYHOST => 0,
    CURLOPT_SSL_VERIFYPEER => 0,
    CURLOPT_HTTPHEADER     => [
        "Origin: https://api.mysoreraceclub.com",
        "Referer: https://api.mysoreraceclub.com/",
    ],
]);

if ($is_m3u8) {
    // MODE A: Playlist — rewrite segment URLs
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $response  = curl_exec($ch);
    $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($http_code != 200) {
        http_response_code($http_code);
        exit("Error: CloudFront returned HTTP $http_code. Keys may have just rotated — retry in a moment.");
    }

    header("Content-Type: application/vnd.apple.mpegurl");
    header("Cache-Control: no-cache, no-store, must-revalidate");

    $base_dir = pathinfo($target_url, PATHINFO_DIRNAME) . '/';
    $lines    = explode("\n", $response);

    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '') continue;
        if ($line[0] === '#') {
            echo $line . "\n";
        } else {
            $abs_url = (strpos($line, 'http') === 0) ? $line : $base_dir . $line;
            echo "proxy.php?url=" . urlencode($abs_url) . "\n";
        }
    }

} else {
    // MODE B: Video segment — pipeline stream directly
    header("Content-Type: video/MP2T");
    header("Cache-Control: public, max-age=3600");

    curl_setopt($ch, CURLOPT_RETURNTRANSFER, false);
    curl_setopt($ch, CURLOPT_WRITEFUNCTION, function($ch, $data) {
        echo $data;
        flush();
        return strlen($data);
    });

    curl_exec($ch);
    curl_close($ch);
}
?>

