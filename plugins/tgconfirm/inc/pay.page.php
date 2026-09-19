<?php
if (!defined('IN_PLUGIN')) exit();
?>
<!DOCTYPE html>
<html lang="zh">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>转账</title>
    <script src="//gw.alipayobjects.com/as/g/h5-lib/alipayjsapi/3.1.1/alipayjsapi.min.js"></script>
</head>
<body>
<script>
    var userId = <?php echo json_encode((string)$channel['appid'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var money = <?php echo json_encode((string)$order['realmoney'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;
    var remark = <?php echo json_encode((string)$order['trade_no'], JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?>;

    function returnApp() {
        AlipayJSBridge.call("exitApp")
    }

    function ready(a) {
        window.AlipayJSBridge ? a && a() : document.addEventListener("AlipayJSBridgeReady", a, !1)
    }

    ready(function () {
        try {
            var a = {
                actionType: "scan",
                u: userId,
                a: money,
                m: remark,
                biz_data: {
                    s: "money",
                    u: userId,
                    a: money,
                    m: remark
                }
            }
        } catch (b) {
            returnApp()
        }
        AlipayJSBridge.call("startApp", {
            appId: "20000123",
            param: a
        }, function (a) { })
    });
    document.addEventListener("resume", function (a) {
        window.location.replace('/pay/qrcode/<?php echo TRADE_NO ?>/');
    });
</script>
</body>
</html>
