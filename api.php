<?php
// Mi Historia, Mi Legado - storage API (flat files, no database needed)
$INVITES = [];   // after a payment, give buyers a code, e.g. ['GRACIAS2026']. [] = open to everyone
$DAYS = 120;     // books untouched this long are erased automatically
$D = __DIR__ . '/data';
if (!is_dir($D)) { @mkdir($D, 0750, true); @file_put_contents("$D/.htaccess", "Require all denied\n"); }
header('Cache-Control: no-store');
function out($a, $c = 200) { http_response_code($c); header('Content-Type: application/json'); exit(json_encode($a)); }
function rm($d) { foreach (glob("$d/*") ?: [] as $f) is_dir($f) ? rm($f) : unlink($f); @rmdir($d); }
function jr($f) { return json_decode(@file_get_contents($f), true) ?: []; }
function jw($f, $a) { file_put_contents($f, json_encode($a, JSON_UNESCAPED_UNICODE), LOCK_EX); }
function tok() { return bin2hex(random_bytes(16)); }

$a = $_GET['a'] ?? '';
$id = strtoupper($_POST['id'] ?? '');

if ($a == 'create') {
  $ok = array_map('strtoupper', $INVITES);
  if ($ok && !in_array(strtoupper(trim($_POST['invite'] ?? '')), $ok)) out(['e' => 1, 'm' => 'code'], 403);
  $name = trim($_POST['name'] ?? ''); $pin = $_POST['pin'] ?? '';
  if ($name === '' || strlen($pin) < 4) out(['e' => 1], 400);
  foreach (glob("$D/*", GLOB_ONLYDIR) ?: [] as $d) if (@filemtime("$d/m.json") < time() - $DAYS * 86400) rm($d);
  do { $id = strtoupper(bin2hex(random_bytes(4))); } while (is_dir("$D/$id"));
  mkdir("$D/$id", 0750); $t = tok();
  jw("$D/$id/m.json", ['name' => substr($name, 0, 120), 'p' => password_hash($pin, PASSWORD_DEFAULT), 't' => hash('sha256', $t)]);
  jw("$D/$id/a.json", []);
  out(['id' => $id, 't' => $t]);
}

if (!preg_match('/^[A-F0-9]{8}$/', $id)) out(['e' => 1], 400);
$d = "$D/$id"; $m = jr("$d/m.json");

if ($a == 'login') {
  if (!$m || !password_verify($_POST['pin'] ?? '', $m['p'])) { sleep(1); out(['e' => 1], 403); }
  $t = tok(); $m['t'] = hash('sha256', $t); jw("$d/m.json", $m);
  out(['id' => $id, 't' => $t]);
}

if (!$m || !hash_equals($m['t'], hash('sha256', $_SERVER['HTTP_X_T'] ?? ''))) out(['e' => 1], 403);
$q = (int)($_POST['q'] ?? 0); $k = ($_POST['k'] ?? '') == 'u' ? 'u' : 'p';

if ($a == 'load') {
  $p = []; $u = [];
  foreach (glob("$d/[pu]*.*") ?: [] as $f)
    if (preg_match('/^([pu])(\d+)\./', basename($f), $x)) { if ($x[1] == 'p') $p[$x[2]] = 1; else $u[$x[2]] = 1; }
  out(['name' => $m['name'], 'a' => (object)jr("$d/a.json"), 'p' => (object)$p, 'u' => (object)$u]);
}
if ($a == 'save') {
  $v = jr("$d/a.json"); $tx = substr($_POST['text'] ?? '', 0, 20000);
  if ($tx === '') unset($v[$q]); else $v[$q] = $tx;
  jw("$d/a.json", $v); touch("$d/m.json"); out(['ok' => 1]);
}
if ($a == 'up') {
  $ext = $_POST['ext'] ?? ''; $f = $_FILES['f'] ?? null;
  if (!in_array($ext, ['jpg', 'webm', 'm4a', 'mp4']) || !$f || $f['size'] > 15 * 1024 * 1024) out(['e' => 1], 400);
  foreach (glob("$d/$k$q.*") ?: [] as $o) unlink($o);
  move_uploaded_file($f['tmp_name'], "$d/$k$q.$ext"); touch("$d/m.json"); out(['ok' => 1]);
}
if ($a == 'file') {
  $f = (glob("$d/$k$q.*") ?: [null])[0]; if (!$f) out(['e' => 1], 404);
  $mt = ['jpg' => 'image/jpeg', 'webm' => 'audio/webm', 'm4a' => 'audio/mp4', 'mp4' => 'audio/mp4'][pathinfo($f, PATHINFO_EXTENSION)];
  header("Content-Type: $mt"); readfile($f); exit;
}
if ($a == 'del') { foreach (glob("$d/$k$q.*") ?: [] as $o) unlink($o); out(['ok' => 1]); }
if ($a == 'erase') { rm($d); out(['ok' => 1]); }
out(['e' => 1], 400);
