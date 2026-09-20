<?php
if (!defined('IN_PLUGIN')) exit();
if (!isset($is_wx)) {
	$is_wx = ($typename === 'wxpay' || $typename === 'wxpay_manual');
}
$title = $is_wx ? '微信扫码转账' : '支付宝扫码转账';
$tip1 = $is_wx ? '请使用微信扫一扫转账' : '请使用支付宝扫一扫转账';
$amount = $order['realmoney'];
$claimed = !empty($claimed);
$code_is_img = $code_url && (strpos($code_url, 'data:image/') === 0 || preg_match('/\.(png|jpe?g|gif|webp)(\?|$)/i', $code_url) || (strpos($code_url, 'http') === 0 && preg_match('/\/.*\.(png|jpe?g|gif|webp)/i', $code_url)));
if ($paytime < 0) $paytime = 0;
$trade_no = $order['trade_no'];
$accent = $is_wx ? '#07C160' : '#1677FF';
$accent_soft = $is_wx ? '#E8F8EF' : '#E8F3FF';
$accent_shadow = $is_wx ? 'rgba(7,193,96,.28)' : 'rgba(22,119,255,.28)';
$brand = $is_wx ? '微信支付' : '支付宝';
if (!isset($open_url)) $open_url = '';
if (!isset($open_label)) $open_label = $is_wx ? '打开微信扫码付款' : '打开支付宝继续付款';
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no, viewport-fit=cover">
<meta name="format-detection" content="telephone=no">
<title><?php echo htmlspecialchars($title) ?></title>
<style>
:root{
  --accent: <?php echo $accent ?>;
  --accent-soft: <?php echo $accent_soft ?>;
  --bg: #f3f5f8;
  --card: #fff;
  --text: #111827;
  --muted: #6b7280;
  --line: #eef0f4;
  --danger: #e11d48;
  --shadow: <?php echo $accent_shadow ?>;
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
.warn{
  display:flex;align-items:flex-start;gap:8px;
  background:#fff4e5;color:#9a3412;border-radius:12px;
  padding:10px 12px;font-size:13px;line-height:1.5;font-weight:600;
}
.warn svg{flex:none;margin-top:1px}
.qr-wrap{position:relative;width:228px;height:228px;margin:22px auto 0}
.qr-frame{
  width:100%;height:100%;border-radius:18px;padding:12px;
  background:#fff;border:1px solid var(--line);
  box-shadow:inset 0 0 0 1px rgba(255,255,255,.6);
  display:flex;align-items:center;justify-content:center;
}
.qr-frame canvas,.qr-frame img{width:204px;height:204px;display:block}
.qr-empty{color:var(--muted);font-size:13px;text-align:center;padding:24px}
.expired{
  display:none;position:absolute;inset:0;border-radius:18px;
  background:rgba(17,24,39,.72);color:#fff;
  flex-direction:column;align-items:center;justify-content:center;gap:6px;font-weight:650;
}
.expired.show{display:flex}
.timer{
  margin:16px 0 0;text-align:center;color:var(--muted);font-size:13px;
}
.timer b{
  font-variant-numeric:tabular-nums;color:var(--text);font-size:16px;margin-left:4px;
}
.hint{margin-top:10px;text-align:center;color:var(--muted);font-size:13px;line-height:1.6}
.wx-guide{
  display:none;margin-top:12px;background:var(--accent-soft);border-radius:12px;
  padding:12px 14px;color:var(--text);font-size:13px;line-height:1.7;font-weight:600;
}
.wx-guide.show{display:block}
.wx-guide ol{margin:0;padding-left:20px}
.claim{margin-top:18px;display:grid;gap:10px}
.claim textarea,.file-btn{
  width:100%;border:1px solid var(--line);border-radius:14px;background:#f8fafc;
  font:inherit;color:var(--text);
}
.claim textarea{min-height:72px;padding:12px 14px;resize:vertical;outline:none}
.claim textarea:focus,.file-btn:focus-within{border-color:var(--accent);background:#fff;box-shadow:0 0 0 3px var(--accent-soft)}
.file-btn{
  display:flex;align-items:center;gap:10px;padding:10px 12px;cursor:pointer;position:relative;
}
.file-btn input{position:absolute;inset:0;opacity:0;cursor:pointer}
.file-ico{
  width:36px;height:36px;border-radius:10px;background:var(--accent-soft);color:var(--accent);
  display:flex;align-items:center;justify-content:center;flex:none;
}
.file-txt{font-size:13px;line-height:1.4}
.file-txt span{display:block;color:var(--muted);font-size:12px}
.preview{display:none;width:72px;height:72px;object-fit:cover;border-radius:10px;margin-left:auto}
.sr-only{position:absolute;width:1px;height:1px;padding:0;margin:-1px;overflow:hidden;clip:rect(0,0,0,0);white-space:nowrap;border:0}
.actions{margin-top:18px;display:none;grid-gap:10px}
.actions.show{display:grid}
.btn{
  appearance:none;border:0;border-radius:14px;background:var(--accent);color:#fff;
  font:inherit;font-size:16px;font-weight:700;padding:14px 16px;cursor:pointer;width:100%;
  box-shadow:0 8px 20px var(--shadow);text-align:center;text-decoration:none;
}
.btn-ghost{
  background:#fff;color:var(--accent);box-shadow:none;
  border:1px solid var(--accent);
}
.btn[disabled]{opacity:.55;cursor:default;box-shadow:none}
.wait{
  display:none;margin-top:18px;text-align:center;padding:8px 8px 4px;
}
.wait.show{display:block}
.pulse{
  width:54px;height:54px;border-radius:50%;margin:0 auto 12px;
  background:var(--accent-soft);color:var(--accent);
  display:flex;align-items:center;justify-content:center;
  animation:pulse 1.6s ease-in-out infinite;
}
.wait h3{font-size:18px;margin-bottom:8px}
.wait p{color:var(--muted);font-size:14px;line-height:1.7}
.meta{margin-top:16px;border-top:1px solid var(--line);padding-top:12px}
.meta summary{list-style:none;cursor:pointer;color:var(--muted);font-size:13px;text-align:center}
.meta summary::-webkit-details-marker{display:none}
.meta dl{margin-top:10px;display:grid;grid-template-columns:88px 1fr;gap:6px 8px;font-size:13px;color:var(--muted)}
.meta dd{color:var(--text);word-break:break-all;text-align:right}
@keyframes pulse{0%,100%{transform:scale(1)}50%{transform:scale(1.06)}}
@media (min-width:480px){.wrap{padding-top:40px}}
</style>
</head>
<body>
<div class="wrap">
  <div class="brand">
    <?php if ($is_wx) { ?>
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M9.5 8.6c.5-3 3.5-5.1 6.8-4.8 3.4.3 6 3.1 6 6.3 0 2.3-1.4 4.3-3.5 5.4l.6 2.2-2.4-1.3c-.7.2-1.4.3-2.2.3-3.4 0-6.2-2.6-6.5-5.8C7.4 10 8.2 9.1 9.5 8.6zM8.2 4.2C4.3 4.6 1.2 7.9 1.2 11.8c0 2.5 1.4 4.7 3.6 6l-.7 2.5 2.7-1.4c.8.2 1.6.3 2.5.3.4 0 .8 0 1.2-.1-1.2-1.3-1.9-3-1.9-4.8 0-4 3.3-7.3 7.4-7.5-1.2-1.7-3.3-2.7-5.8-2.6z"/></svg>
    <?php } else { ?>
    <svg viewBox="0 0 24 24" fill="currentColor" aria-hidden="true"><path d="M5 4h14a1 1 0 0 1 1 1v4.2c-2.4 1.4-6.3 3.4-8.8 4.2C8.4 12.5 4.7 10.7 4 10.2V5a1 1 0 0 1 1-1zm-.8 8.4c.9.6 4.8 2.7 7.8 3.6 3.3-.9 7.4-3.1 8.8-4v7a1 1 0 0 1-1 1H5a1 1 0 0 1-1-1v-6.3c.1-.1.1-.2.2-.3z"/></svg>
    <?php } ?>
    <?php echo htmlspecialchars($brand) ?>
  </div>

  <div class="card">
    <div class="amount-label">请转账</div>
    <div class="amount"><small>¥</small><?php echo htmlspecialchars($amount) ?></div>
    <div class="warn">
      <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><circle cx="12" cy="12" r="9"/><path d="M12 8v5M12 16h.01"/></svg>
      请按此金额原样转账，必须完全一致才能完成支付
    </div>

    <div class="qr-wrap">
      <div class="qr-frame" id="qrcode"></div>
      <div class="expired" id="qrExpiredOverlay" role="status" aria-live="polite">
        <div>已超时</div>
        <div style="font-size:13px;font-weight:500">请返回重新发起支付</div>
      </div>
    </div>
    <div class="timer" aria-live="polite">剩余时间<b id="remain">00:00:00</b></div>
    <div class="hint" id="scanHint"><?php echo htmlspecialchars($tip1) ?></div>
    <?php if ($is_wx) { ?>
    <div class="wx-guide" id="wxGuide">
      <ol>
        <li>先保存上方二维码到相册</li>
        <li>再点下方按钮打开微信，用扫一扫付款</li>
      </ol>
    </div>
    <?php } ?>

    <div class="actions" id="openApp">
      <?php if ($is_wx) { ?>
      <button type="button" class="btn btn-ghost" id="saveQrBtn">保存二维码</button>
      <?php } ?>
      <a class="btn" id="openBtn" href="javascript:void(0)"><?php echo htmlspecialchars($open_label) ?></a>
    </div>

    <div id="claimBox" class="claim">
      <label class="sr-only" for="claimNote">转账说明</label>
      <textarea id="claimNote" maxlength="200" placeholder="选填：转账说明，如账号后四位"></textarea>
      <label class="file-btn">
        <input type="file" id="claimFile" accept="image/jpeg,image/png,image/webp,image/gif">
        <span class="file-ico">
          <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="5" width="18" height="14" rx="2"/><circle cx="8.5" cy="10.5" r="1.5"/><path d="m21 15-5-5-8 8"/></svg>
        </span>
        <span class="file-txt" id="fileTxt">上传转账截图<span>选填，jpg / png，不超过 5MB</span></span>
        <img id="claimPreview" class="preview" alt="">
      </label>
      <button type="button" class="btn" id="claimBtn">我已支付</button>
    </div>

    <div id="waitBox" class="wait">
      <div class="pulse">
        <svg width="26" height="26" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M12 6v6l4 2"/><circle cx="12" cy="12" r="9"/></svg>
      </div>
      <h3>等待确认付款到账</h3>
      <p>现在可以放心关闭页面<br>确认大概需要 1–240 分钟，请耐心等待</p>
    </div>

    <details class="meta">
      <summary>订单详情</summary>
      <dl>
        <dt>商品</dt><dd><?php echo htmlspecialchars($order['name']) ?></dd>
        <dt>订单号</dt><dd><?php echo htmlspecialchars($order['trade_no']) ?></dd>
        <dt>创建时间</dt><dd><?php echo htmlspecialchars($order['addtime']) ?></dd>
      </dl>
    </details>
  </div>
</div>
<script src="<?php echo $cdnpublic ?>jquery/1.12.4/jquery.min.js"></script>
<script src="<?php echo $cdnpublic ?>layer/3.1.1/layer.js"></script>
<script src="<?php echo $cdnpublic ?>jquery.qrcode/1.0/jquery.qrcode.min.js"></script>
<script>
var tradeNo = <?php echo json_encode($trade_no, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var claimed = <?php echo $claimed ? 'true' : 'false' ?>;
var code_url = <?php echo json_encode((string)$code_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var code_is_img = <?php echo $code_is_img ? 'true' : 'false' ?>;
var paymentType = <?php echo json_encode((string)$typename, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var open_url = <?php echo json_encode((string)$open_url, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
var qrcode = document.getElementById('qrcode');
if (!code_url) {
  var empty = document.createElement('div');
  empty.className = 'qr-empty';
  empty.textContent = '收款码未配置，请按上方金额转账';
  qrcode.appendChild(empty);
} else if (code_is_img) {
  var image = document.createElement('img');
  image.src = code_url;
  image.alt = '收款码';
  qrcode.appendChild(image);
} else {
  $(qrcode).qrcode({text: code_url, width: 204, height: 204, foreground: "#111827", background: "#ffffff", typeNumber: -1});
}
function isMobile() {
  return /iPhone|iPad|Android/i.test(navigator.userAgent);
}
function saveQr() {
  var frame = document.getElementById('qrcode');
  var canvas = frame.querySelector('canvas');
  var img = frame.querySelector('img');
  var url = '';
  if (canvas) url = canvas.toDataURL('image/png');
  else if (img) url = img.src;
  else {
    layer.msg('暂无二维码可保存');
    return;
  }
  var wrap = $('<div style="padding:16px;text-align:center"></div>');
  wrap.append($('<img alt="收款码" style="width:240px;height:240px">').attr('src', url));
  wrap.append('<p style="margin-top:10px;color:#6b7280;font-size:13px">长按图片保存到相册</p>');
  layer.open({type: 1, title: false, shadeClose: true, content: wrap, area: '280px'});
}
function showWait() {
  claimed = true;
  $('#claimBox').hide();
  $('#openApp').hide();
  $('#wxGuide').hide();
  $('#waitBox').addClass('show');
}
function paidJump(backurl) {
  layer.msg('支付成功，正在跳转中...', {icon: 16, shade: 0.1, time: 15000});
  setTimeout(function () { window.location.href = backurl || '/payok.html'; }, 800);
}
function loadmsg() {
  $.ajax({
    type: "GET",
    dataType: "json",
    url: "/pay/status/" + tradeNo + "/",
    success: function (data) {
      if (data.code == 1) {
        $.getJSON("/getshop.php", {type: paymentType, trade_no: tradeNo}, function (d) {
          paidJump(d && d.backurl);
        }).fail(function () { paidJump(); });
      } else if (data.code == 0) {
        showWait();
        setTimeout(loadmsg, 2000);
      } else if (data.code == -2) {
        document.getElementById('qrExpiredOverlay').classList.add('show');
        $('#claimBox').hide();
        $('#openApp').hide();
        $('#wxGuide').hide();
      } else if (data.code == -4) {
        var closedOverlay = document.getElementById('qrExpiredOverlay');
        closedOverlay.firstElementChild.textContent = '订单已关闭';
        closedOverlay.lastElementChild.textContent = '请返回重新发起支付';
        closedOverlay.classList.add('show');
        $('#claimBox').hide();
        $('#openApp').hide();
        $('#wxGuide').hide();
      } else {
        setTimeout(loadmsg, 2000);
      }
    },
    error: function () { setTimeout(loadmsg, 2000); }
  });
}
$('#claimFile').on('change', function () {
  var f = this.files && this.files[0];
  if (!f) {
    $('#claimPreview').hide();
    var emptyFile = document.getElementById('fileTxt');
    emptyFile.textContent = '上传转账截图';
    var emptyHint = document.createElement('span');
    emptyHint.textContent = '选填，jpg / png，不超过 5MB';
    emptyFile.appendChild(emptyHint);
    return;
  }
  if (f.size > 5 * 1024 * 1024) {
    layer.msg('图片不能超过 5MB');
    this.value = '';
    return;
  }
  var fileText = document.getElementById('fileTxt');
  fileText.textContent = f.name;
  var fileHint = document.createElement('span');
  fileHint.textContent = '已选择，可更换';
  fileText.appendChild(fileHint);
  $('#claimPreview').attr('src', URL.createObjectURL(f)).show();
});
$('#claimBtn').on('click', function () {
  var btn = $(this);
  if (btn.prop('disabled')) return;
  btn.prop('disabled', true).text('提交中...');
  var fd = new FormData();
  fd.append('note', $('#claimNote').val() || '');
  var f = document.getElementById('claimFile').files[0];
  if (f) fd.append('proof', f);
  $.ajax({
    type: 'POST',
    url: '/pay/claimed/' + tradeNo + '/',
    data: fd,
    processData: false,
    contentType: false,
    dataType: 'json',
    success: function (data) {
      if (data.code == 1) {
        $.getJSON("/getshop.php", {type: paymentType, trade_no: tradeNo}, function (d) {
          paidJump(d && d.backurl);
        }).fail(function () { paidJump(); });
        return;
      }
      if (data.code == 0) {
        showWait();
        if (data.paytime > 0) startCountdown(data.paytime);
        setTimeout(loadmsg, 2000);
        return;
      }
      layer.msg(data.msg || '提交失败');
      btn.prop('disabled', false).text('我已支付');
    },
    error: function () {
      layer.msg('提交失败，请稍后重试');
      btn.prop('disabled', false).text('我已支付');
    }
  });
});
function startCountdown(duration) {
  if (window.countdownInterval) clearInterval(window.countdownInterval);
  var timer = duration;
  var el = document.getElementById('remain');
  var overlay = document.getElementById('qrExpiredOverlay');
  if (timer > 0) overlay.classList.remove('show');
  var tick = function () {
    if (timer <= 0) {
      el.textContent = '00:00:00';
      overlay.classList.add('show');
      $('#claimBox').hide();
      $('#openApp').hide();
      $('#wxGuide').hide();
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
window.onload = function () {
  if (claimed) {
    showWait();
  } else if (isMobile() && open_url) {
    $('#openApp').addClass('show');
    $('#openBtn').attr('href', open_url);
    $('#saveQrBtn').on('click', saveQr);
    if ($('#wxGuide').length) {
      $('#scanHint').hide();
      $('#wxGuide').addClass('show');
    }
  }
  setTimeout(loadmsg, 2000);
  startCountdown(<?php echo intval($paytime) ?>);
};
</script>
</body>
</html>
