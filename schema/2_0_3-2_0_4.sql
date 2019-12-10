/* 
 * ******************************************************************************
 * *  Copyright 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */
/**
 * Author:  swinter
 * Created: 10.12.2019
 */

CREATE TABLE `federation_servercerts` (
  `federation_id` varchar(16) NOT NULL DEFAULT 'DEFAULT',
  `ca_name` varchar(16),
  `request_serial` int(11) NOT NULL,
  `distinguished_name` varchar(255) NOT NULL,
  `status` enum('REQUESTED','ISSUED','REVOKED'),
  `expiry` date,
  `certificate` longblob,
  `revocation_pin` varchar(16),
  UNIQUE KEY `cert_id` (`ca_name`,`request_serial`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8;
