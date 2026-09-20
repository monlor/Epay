<?php
if (!defined('IN_PLUGIN')) exit();
$h = function($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); };
$is_image = ($pay_data['qr_type'] ?? '') === 'image';
$show_surcharge = ($pay_data['payable'] ?? '') !== ($pay_data['requested'] ?? '');
$is_transfer = ($pay_data['mode'] ?? '') === 'transfer';
$open_url = $pay_data['open_url'] ?? '';
$title = $is_transfer ? '支付宝转账支付' : '支付宝经营码支付';
$tip = $is_transfer ? '请使用支付宝扫码转账，备注不要修改' : '请使用支付宝扫码，并输入上方精确金额';
if ($paytime < 0) $paytime = 0;
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?php echo $h($title) ?></title>
<style>
:root{
  --accent: #1677FF;
  --accent-soft: #E8F3FF;
  --bg: #f3f5f8;
  --card: #fff;
  --text: #111827;
  --muted: #6b7280;
  --line: #eef0f4;
  --shadow: rgba(22,119,255,.28);
  --radius: 20px;
}
*{box-sizing:border-box;margin:0;padding:0}
html,body{min-height:100%}
body{
  font-family:-apple-system,BlinkMacSystemFont,"Segoe UI","PingFang SC","Hiragino Sans GB","Noto Sans SC","Microsoft YaHei",sans-serif;
  background:radial-gradient(1200px 600px at 50% -120px, var(--accent-soft), var(--bg) 55%);
  color:var(--text);
  -webkit-font-smoothing:antialiased;
}
.wrap{max-width:440px;margin:0 auto;padding:20px 16px 40px}
.brand{
  display:flex;align-items:center;justify-content:center;gap:8px;
  color:var(--accent);font-weight:650;font-size:15px;letter-spacing:.02em;margin-bottom:16px;
}
.brand svg{width:22px;height:22px}
.card{
  background:var(--card);
  border-radius:var(--radius);
  box-shadow:0 12px 40px rgba(17,24,39,.06);
  padding:28px 22px 22px;
}
.amount-label{text-align:center;color:var(--muted);font-size:13px}
.amount{
  text-align:center;font-variant-numeric:tabular-nums;
  font-size:42px;font-weight:760;letter-spacing:-.04em;line-height:1.15;margin:6px 0 12px;
}
.amount small{font-size:22px;margin-right:2px;font-weight:700}
.warn,.memo{
  display:flex;align-items:flex-start;gap:8px;
  border-radius:12px;padding:10px 12px;font-size:13px;line-height:1.5;font-weight:600;
}
.warn{background:#fff4e5;color:#9a3412}
.memo{background:var(--accent-soft);color:#1d4ed8;margin-top:8px}
.warn svg,.memo svg{flex:none;margin-top:1px}
.qr-wrap{position:relative;width:228px;height:228px;margin:22px auto 0}
.qr-frame{
  width:100%;height:100%;border-radius:18px;padding:12px;
  background:#fff;border:1px solid var(--line);
  display:flex;align-items:center;justify-content:center;
}
.qr-frame canvas,.qr-frame img{width:204px;height:204px;display:block;object-fit:contain}
.expired{
  display:none;position:absolute;inset:0;border-radius:18px;
  background:rgba(17,24,39,.72);color:#fff;
  flex-direction:column;align-items:center;justify-content:center;gap:6px;font-weight:650;
}
.expired.show{display:flex}
.timer{margin:16px 0 0;text-align:center;color:var(--muted);font-size:13px}
.timer b{font-variant-numeric:tabular-nums;color:var(--text);font-size:16px;margin-left:4px}
.hint{margin-top:10px;text-align:center;color:var(--muted);font-size:13px;line-height:1.6}
.actions{margin-top:18px;display:none;grid-gap:10px}
.actions.show{display:grid}
.btn{
  appearance:none;border:0;border-radius:14px;background:var(--accent);color:#fff;
  font:inherit;font-size:16px;font-weight:700;padding:14px 16px;cursor:pointer;width:100%;
  box-shadow:0 8px 20px var(--shadow);text-align:center;text-decoration:none;
}
.guide{
  display:none;position:fixed;inset:0;z-index:20;background:rgba(17,24,39,.72);
  color:#fff;padding:24px 20px;text-align:center;
}
.guide.show{display:flex;align-items:flex-start;justify-content:flex-end}
.guide img{max-width:220px}
.meta{margin-top:16px;border-top:1px solid var(--line);padding-top:12px}
.meta summary{list-style:none;cursor:pointer;color:var(--muted);font-size:13px;text-align:center}
.meta summary::-webkit-details-marker{display:none}
.meta dl{margin-top:10px;display:grid;grid-template-columns:88px 1fr;gap:6px 8px;font-size:13px;color:var(--muted)}
.meta dd{color:var(--text);word-break:break-all;text-align:right}
@media (min-width:480px){.wrap{padding-top:40px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 4h14a1 1 0 0 1 1 1v4.2c-2.4 1.4-6.3 3.4-8.8 4.2C8.4 12.5 4.7 10.7 4 10.2V5a1 1 0 0 1 1-1zm-.8 8.4c.9.6 4.8 2.7 7.8 3.6 3.3-.9 7.4-3.1 8.8-4v7a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6.3c.1-.1.1-.2.2-.3z"/></svg>
    支付宝
  </div>
  <div class="card">
    <div class="amount-label"><?php echo $is_transfer ? '请转账' : '请支付'; ?></div>
    <div class="amount"><small>¥</small><?php echo $h($pay_data['payable']); ?></div>
    <?php if($show_surcharge){ ?>
    <div class="warn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      请按此金额准确支付。商户金额为 ¥<?php echo $h($pay_data['requested']); ?>，系统为本单分配了唯一分位金额。
    </div>
    <?php } elseif($is_transfer){ ?>
    <div class="warn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      请按此金额原样转账，必须完全一致才能完成支付
    </div>
    <?php } ?>
    <?php if($is_transfer){ ?>
    <div class="memo">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M4 7h16M4 12h10M4 17h7"/></svg>
      转账备注必须保持为 <?php echo $h($order['trade_no']); ?>，不要修改
    </div>
    <?php } ?>

    <div class="qr-wrap">
      <div class="qr-frame" id="qrcode"></div>
      <div class="expired" id="qrExpiredOverlay" role="status">
        <div>已超时</div>
        <div style="font-size:13px;font-weight:500">请返回重新发起支付</div>
      </div>
    </div>
    <div class="timer">剩余时间<b id="remain">00:00:00</b></div>
    <div class="hint"><?php echo $h($tip); ?></div>

    <div class="actions" id="openApp">
      <a class="btn" id="openBtn" href="javascript:void(0)">打开支付宝继续付款</a>
    </div>

    <details class="meta">
      <summary>订单详情</summary>
      <dl>
        <dt>商品</dt><dd><?php echo $h($order['name']); ?></dd>
        <dt>订单号</dt><dd><?php echo $h($order['trade_no']); ?></dd>
        <dt>创建时间</dt><dd><?php echo $h($order['addtime']); ?></dd>
      </dl>
    </details>
  </div>
</div>
<div class="guide" id="wxGuide"><img src="/assets/img/guide2.png" alt="请在浏览器打开"></div>
<script src="<?php echo $cdnpublic ?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic ?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
var code_url = <?php echo json_encode((string)$code_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
var open_url = <?php echo json_encode((string)$open_url, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE); ?>;
var code_type = <?php echo $is_image ? 1 : 0; ?>;
var status_url = <?php echo json_encode('/pay/status/'.$order['trade_no'].'/'); ?>;
var url_scheme = open_url ? open_url : (code_type == 0 ? ('alipays://platformapi/startapp?appId=20000067&url=' + encodeURIComponent(code_url)) : '');
var qrcode = document.getElementById('qrcode');
if (code_type == 1) {
  var image = document.createElement('img');
  image.src = code_url;
  image.alt = '收款码';
  qrcode.appendChild(image);
} else {
  $(qrcode).qrcode({text: code_url, width: 204, height: 204, foreground: "#111827", background: "#ffffff", typeNumber: -1});
}
function paidJump(backurl) {
  layer.msg('支付成功，正在跳转中...', {icon: 16, shade: 0.1, time: 15000});
  setTimeout(function(){ window.location.href = backurl || '/payok.html'; }, 800);
}
function markClosed(text) {
  var overlay = document.getElementById('qrExpiredOverlay');
  overlay.firstElementChild.textContent = text || '已超时';
  overlay.classList.add('show');
}
function loadmsg() {
  $.ajax({
    type: "GET",
    dataType: "json",
    url: status_url,
    success: function(data) {
      if (data.code == 1) {
        paidJump(data.backurl);
      } else if (data.code == -4) {
        markClosed('订单已关闭');
      } else {
        setTimeout(loadmsg, 2000);
      }
    },
    error: function() { setTimeout(loadmsg, 2000); }
  });
}
function isMobile() {
  var ua = navigator.userAgent;
  return /iPhone|iPad|Android/i.test(ua);
}
function startCountdown(duration) {
  var timer = duration;
  var el = document.getElementById('remain');
  var overlay = document.getElementById('qrExpiredOverlay');
  var tick = function() {
    if (timer <= 0) {
      el.textContent = '00:00:00';
      overlay.classList.add('show');
      clearInterval(window.countdownInterval);
      return;
    }
    var h = Math.floor(timer / 3600), m = Math.floor((timer % 3600) / 60), s = timer % 60;
    el.textContent = (h < 10 ? '0' : '') + h + ':' + (m < 10 ? '0' : '') + m + ':' + (s < 10 ? '0' : '') + s;
    timer--;
  };
  tick();
  window.countdownInterval = setInterval(tick, 1000);
}
window.onload = function() {
  if (isMobile() && url_scheme) {
    $('#openApp').addClass('show');
    if (navigator.userAgent.indexOf('MicroMessenger/') > 0) {
      $('#openBtn').on('click', function(){ $('#wxGuide').addClass('show'); });
    } else {
      $('#openBtn').attr('href', url_scheme);
    }
  }
  $('#wxGuide').on('click', function(){ $(this).removeClass('show'); });
  setTimeout(loadmsg, 2000);
  startCountdown(<?php echo intval($paytime); ?>);
};
</script>
</body>
</html>
