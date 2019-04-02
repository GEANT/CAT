/* 
 * ******************************************************************************
 * *  Copyright 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */
/**
 * Author:  swinter
 * Created: 19.03.2019
 */

ALTER TABLE `silverbullet_certificate` ADD COLUMN `extrainfo` longblob DEFAULT NULL;

CREATE TABLE `diagnosticrun` (
  `test_id` VARCHAR(128) NOT NULL,
  `last_touched` TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  `realm` VARCHAR(128) NOT NULL,
  `visited_flr` VARCHAR(10) DEFAULT NULL, 
  `visited_hotspot` VARCHAR(128) DEFAULT NULL,
  `suspects` LONGBLOB DEFAULT NULL,
  `evidence` LONGBLOB DEFAULT NULL,
  `questionsasked` LONGBLOB DEFAULT NULL,
  `concluded` TINYINT(1) DEFAULT 0,
  PRIMARY KEY (`test_id`))
ENGINE = InnoDB CHARSET=utf8;
