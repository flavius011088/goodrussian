<?php
// video.php - Обработчик для отдачи видеофайлов
header('Content-Type: video/mp4');
header('Accept-Ranges: bytes');

$video_file = $_GET['file'] ?? '';
$allowed_files = ['news_5_1.mp4', 'news_7_7.mp4'];

if (in_array($video_file, $allowed_files)) {
    $file_path = __DIR__ . '/video/' . $video_file;
    
    if (file_exists($file_path)) {
        readfile($file_path);
    } else {
        http_response_code(404);
        echo 'Video file not found';
    }
} else {
    http_response_code(403);
    echo 'Access denied';
}
?>