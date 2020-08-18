/* 
 * ******************************************************************************
 * *  Copyright 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */
/**
 * Author:  swinter
 * Created: 19.08.2019
 */

DELETE FROM profile_option WHERE option_name = "device-specific:builtin_ttls";
DELETE FROM profile_option_dict WHERE name = "device-specific:builtin_ttls";

ALTER TABLE silverbullet_certificate ADD COLUMN `extrainfo` longblob DEFAULT NULL;