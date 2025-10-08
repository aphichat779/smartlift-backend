<?php
// api/lifts/sse_diag.php — ping ล้วน ๆ ไม่พึ่ง DB/Redis
declare(strict_types=1);

header('Content-Type: text/event-stream; charset=utf-8');
header('Cache-Control: no-cache, no-transform');
header('Connection: keep-alive');
header('X-Accel-Buffering: no');    // nginx
header('Keep-Alive: timeout=60, max=1000');

// ปิดบัฟเฟอร์ทุกชั้น
@ini_set('output_buffering', 'off');
@ini_set('zlib.output_compression', '0');
if (function_exists('apache_setenv')) @apache_setenv('no-gzip', '1');
while (ob_get_level() > 0) @ob_end_flush();
if (function_exists('ob_implicit_flush')) @ob_implicit_flush(true);

// เติม padding 2KB กัน proxy/บราวเซอร์แคชค้าง
echo ":" . str_repeat(" ", 2048) . "\n";
echo "retry: 2000\n\n";
@ob_flush(); @flush();

$i = 0;
while ($i < 30) { // ส่ง 30 ครั้ง (ประมาณ 60 วิ)
  $i++;
  echo "event: ping\n";
  echo "data: {\"i\": $i, \"t\": \"" . gmdate('c') . "\"}\n\n";
  @ob_flush(); @flush();
  sleep(2);
}
