/* 
 * ******************************************************************************
 * *  Copyright 2011-12 DANTE Ltd. on behalf of the GN3 consortium
 * ******************************************************************************
 * *  License: see the LICENSE file in the root directory of this release
 * ******************************************************************************
 */
/**
 * Author:  swinter
 * Created: 17.12.2015
 */
INSERT INTO `profile_option_dict` VALUES 
('fed:realname','a friendly display name of the NRO/federation','string', 'ML'),
('fed:logo_file','logo of the NRO/federation','file', NULL),
('fed:css_file','custom CSS to be applied on any skin','file',NULL),
('fed:desired_skin','UI skin to use - if not exist, fall back to default','string',NULL);
