/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2) and from the Horizon Europe programme 
 * under Grant Agreement No: 101100680  (GN5-1).
 * On behalf of the aforementioned projects, GEANT Association is the sole owner
 * of the copyright in all material which was developed by a member of the GÉANT
 * project. GÉANT Vereniging (Association) is registered with the Chamber of 
 * Commerce in Amsterdam with registration number 40535155 and operates in the 
 * UK as a branch of GÉANT Vereniging.
 * 
 * Registered office: Hoekenrode 3, 1102BR Amsterdam, The Netherlands. 
 * UK branch address: City House, 126-130 Hills Road, Cambridge CB2 1PQ, UK
 *
 * License: see the web/copyright.inc.php file in the file structure or
 *          <base_url>/copyright.php after deploying the software
 */

/**
 * Author:  twoln
 * Created: 16 Mau 2024
 */


UPDATE profile_option_dict SET flag='SPECIFIC' WHERE name = 'device-specific:geteduroam';
UPDATE profile_option_dict SET flag='SPECIFIC' WHERE name = 'device-specific:geantlink';
ALTER TABLE federation_option ADD KEY federation_id (federation_id);
ALTER TABLE profile_option ADD KEY profile_id (profile_id);
ALTER TABLE profile_option ADD KEY device_id (device_id);
ALTER TABLE profile ADD KEY showtime (showtime);
ALTER TABLE profile ADD KEY sufficient_config (sufficient_config);
ALTER TABLE profile ADD KEY openroaming (openroaming);
ALTER TABLE institution ADD KEY country (country);
ALTER TABLE institution ADD KEY external_db_id (external_db_id);
ALTER TABLE institution ADD KEY external_db_syncstate (external_db_syncstate);
ALTER TABLE invitations ADD KEY cat_institution_id (cat_institution_id);
ALTER TABLE invitations ADD KEY invite_created (invite_created);
ALTER TABLE invitations ADD KEY used (used);
