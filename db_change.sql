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

ALTER TABLE `shop_goods_mill`
	ADD COLUMN `sale_type` TINYINT NOT NULL DEFAULT 1 AFTER `ipfs_type`;
	ALTER TABLE `shop_goods_mill`
	CHANGE COLUMN `sale_type` `category` TINYINT(4) NOT NULL DEFAULT '1' AFTER `ipfs_type`;
	ALTER TABLE `shop_goods_mill_order`
	ADD COLUMN `method` TINYINT(1) NOT NULL DEFAULT '1' AFTER `type`;

	ALTER TABLE `shop_goods_mill`
	ADD COLUMN `rengou_begin_day` TIME NULL DEFAULT NULL AFTER `rengou_end`,
	ADD COLUMN `rengou_end_day` TIME NULL DEFAULT NULL AFTER `rengou_begin_day`;

	ALTER TABLE `shop_goods_mill_order`
	ADD COLUMN `efee_limit` DATETIME NULL DEFAULT NULL AFTER `buy_time`;
	ALTER TABLE `shop_goods_mill_order`
	CHANGE COLUMN `efee_limit` `efee_limit` DATE NULL DEFAULT NULL AFTER `buy_time`;



	/*Buy Rebate*/
	ALTER TABLE `shop_goods_mill`
	ADD COLUMN `rp1` FLOAT NOT NULL DEFAULT '5' AFTER `suanli`,
	ADD COLUMN `rp2` FLOAT NOT NULL DEFAULT '3' AFTER `rp1`,
	ADD COLUMN `rp3` FLOAT NOT NULL DEFAULT '1' AFTER `rp2`,
	ADD COLUMN `r1` FLOAT NOT NULL DEFAULT '5' AFTER `rp3`,
	ADD COLUMN `r2` FLOAT NOT NULL DEFAULT '3' AFTER `r1`,
	ADD COLUMN `r3` FLOAT NOT NULL DEFAULT '1' AFTER `r2`;

/* Rebate time*/
ALTER TABLE `shop_goods_mill`
	ADD COLUMN `rebate_at` INT(11) NOT NULL DEFAULT '1' AFTER `zhouqi`;

	INSERT INTO `ethereumuniswap_`.`shop_finance_types` (`id`, `label`) VALUES ('36', '购买云算力');