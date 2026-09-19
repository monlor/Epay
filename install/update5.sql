INSERT INTO `pre_type` (`name`, `device`, `showname`, `status`)
SELECT 'alipay_manual', 0, '支付宝转账', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pre_type` WHERE `name`='alipay_manual' AND `device`=0);

INSERT INTO `pre_type` (`name`, `device`, `showname`, `status`)
SELECT 'wxpay_manual', 0, '微信转账', 1 FROM DUAL
WHERE NOT EXISTS (SELECT 1 FROM `pre_type` WHERE `name`='wxpay_manual' AND `device`=0);

UPDATE `pre_plugin` SET `types`='alipay,alipay_manual,wxpay,wxpay_manual' WHERE `name`='tgconfirm';
