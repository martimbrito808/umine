ALTER TABLE `shop_goods_wealth`
	CHANGE COLUMN `rengou_begin` `rengou_begin` TIME NOT NULL COMMENT '认购期 开始时间' AFTER `rengouedu`,
	CHANGE COLUMN `rengou_end` `rengou_end` TIME NOT NULL COMMENT '认购期 结束时间' AFTER `rengou_begin`;

ALTER TABLE `shop_goods_mill`
	ADD COLUMN `zhouqi` TINYINT NOT NULL DEFAULT 1 AFTER `oc_type`;

	ALTER TABLE `shop_goods_mill`
	ADD COLUMN `rengou_begin` TIME NULL DEFAULT NULL AFTER `shangjia_time`,
	ADD COLUMN `rengou_end` TIME NULL DEFAULT NULL AFTER `rengou_begin`;

	ALTER TABLE `shop_goods_mill`
	CHANGE COLUMN `zhouqi` `zhouqi` INT NOT NULL DEFAULT 1 AFTER `oc_type`;

	ALTER TABLE `shop_goods_mill_order`
	ADD COLUMN `zhouqi` INT(11) NOT NULL DEFAULT '0' AFTER `num`;
	
	ALTER TABLE `shop_goods_mill_order`
	ADD COLUMN `status` TINYINT(1) NOT NULL DEFAULT '1' AFTER `type`;
	
	ALTER TABLE `shop_goods_wealth`
	ADD COLUMN `sale_type` TINYINT(1) NOT NULL DEFAULT '1' AFTER `type`;

ALTER TABLE `shop_goods_wealth`
	ADD COLUMN `apr` INT(11) NULL DEFAULT NULL AFTER `apr_3`;
