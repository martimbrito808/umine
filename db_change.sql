ALTER TABLE `shop_goods_wealth`
	CHANGE COLUMN `rengou_begin` `rengou_begin` TIME NOT NULL COMMENT '认购期 开始时间' AFTER `rengouedu`,
	CHANGE COLUMN `rengou_end` `rengou_end` TIME NOT NULL COMMENT '认购期 结束时间' AFTER `rengou_begin`;

ALTER TABLE `shop_goods_mill`
	ADD COLUMN `zhouqi` TINYINT NOT NULL DEFAULT 1 AFTER `oc_type`;