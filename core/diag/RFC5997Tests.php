<?php

/*
 * *****************************************************************************
 * Contributions to this work were made on behalf of the GÉANT project, a 
 * project that has received funding from the European Union’s Framework 
 * Programme 7 under Grant Agreements No. 238875 (GN3) and No. 605243 (GN3plus),
 * Horizon 2020 research and innovation programme under Grant Agreements No. 
 * 691567 (GN4-1) and No. 731122 (GN4-2).
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

namespace core\diag;

use \Exception;

/**
 * Test suite to verify that a given NAI realm has NAPTR records according to
 * consortium-agreed criteria
 * Can only be used if \config\Diagnostics::RADIUSTESTS is configured.
 *
 * @author Stefan Winter <stefan.winter@restena.lu>
 * @author Tomasz Wolniewicz <twoln@umk.pl>
 *
 * @license see LICENSE file in root directory
 *
 * @package Developer
 */
class RFC5997Tests extends AbstractTest
{

    const PACKET_TYPE_STATUS_SERVER = "\xc";
    const PACKET_TYPE_ACCESS_ACCEPT = "\x2";
    const PACKET_LENGTH = "\x0\x2b"; // only calid for string CAT in NAS-Id
    const ATTRIBUTE_NAS_IDENTIFIER = "\x20";
    const LENGTH_NAS_IDENTIFIER = "\x5"; // only valid for string CAT
    const VALUE_NAS_IDENTIFIER = "CAT";
    const ATTRIBUTE_MESSAGE_AUTHENTICATOR = "\x50";
    const LENGTH_MESSAGE_AUTHENTICATOR = "\x12"; // 18
    const CONNECTION_TIMEOUT = 5;

    /**
     * IP address of candidate hotspot
     * 
     * @var string
     */
    private $ipAddr;

    /**
     * port of candidate hotspot
     * 
     * @var integer
     */
    private $port;

    /**
     * shared secret of candidate hotspot
     * 
     * @var string
     */
    private $secret;

    /**
     * Sets up the instance for testing of a candidate hotspots
     * 
     * @param string $ipAddr IP of candidate hotspot
     * @param int    $port   port of candidate hotspot
     * @param string $secret shared secret of candidate hotspot
     */
    public function __construct($ipAddr, $port, $secret)
    {
        parent::__construct();
        $this->ipAddr = $ipAddr;
        $this->port = $port;
        $this->secret = $secret;
    }

    /**
     * execute Status-Server and note the result
     * 
     * @return integer the status code
     * @throws Exception
     */
    public function statusServerCheck()
    {
        // request authenticator and other variable content
        $reqAuthenticator = random_bytes(16);
        $packetIdentifier = random_bytes(1);
        // construct Status-Server packet
        $prePacket = RFC5997Tests::PACKET_TYPE_STATUS_SERVER .
                $packetIdentifier .
                RFC5997Tests::PACKET_LENGTH .
                $reqAuthenticator .
                RFC5997Tests::ATTRIBUTE_NAS_IDENTIFIER .
                RFC5997Tests::LENGTH_NAS_IDENTIFIER .
                RFC5997Tests::VALUE_NAS_IDENTIFIER;
        $sigPacket = $prePacket .
                RFC5997Tests::ATTRIBUTE_MESSAGE_AUTHENTICATOR .
                RFC5997Tests::LENGTH_MESSAGE_AUTHENTICATOR .
                "\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0\x0";
        $authPacket = $prePacket .
                RFC5997Tests::ATTRIBUTE_MESSAGE_AUTHENTICATOR .
                RFC5997Tests::LENGTH_MESSAGE_AUTHENTICATOR .
                hash_hmac("md5", $sigPacket, $this->secret, TRUE);
        $connectErrorNumber = 0;
        $connectErrorString = "";
        $netHandle = fsockopen("udp://" . $this->ipAddr, $this->port, $connectErrorNumber, $connectErrorString, RFC5997Tests::CONNECTION_TIMEOUT);
        if ($netHandle === FALSE) {
            throw new Exception("Unable to establish UDP socket resource. Error number was $connectErrorNumber, '$connectErrorString'");
        }
        stream_set_timeout($netHandle, RFC5997Tests::CONNECTION_TIMEOUT);
        $written = fwrite($netHandle, $authPacket);
        if ($written === FALSE || $written != 43) {
            throw new Exception("Unable to write packet to socket ($written)!");
        }
        $read = fread($netHandle, 4096);
        if ($read === FALSE) {
            return AbstractTest::RETVAL_NO_RESPONSE;
        }
        if ($read === FALSE || strlen($read) < 20 || $read[0] != RFC5997Tests::PACKET_TYPE_ACCESS_ACCEPT || $read[1] != $packetIdentifier) {
            // we didn't get any useful response. The hotspot is not operational.
            return AbstractTest::RETVAL_INVALID;
        }
        // check the response authenticator to prevent spoofing.
        $sigResponse = RFC5997Tests::PACKET_TYPE_ACCESS_ACCEPT .
                $packetIdentifier .
                $read[2] . $read[3] .
                $reqAuthenticator .
                substr($read, 20) .
                $this->secret;
        $expected = hash("md5", $sigResponse, TRUE);
        if ($expected != substr($read, 4, 16)) {
            throw new Exception("Received incorrect Response-Authenticator. Something is very wrong.");
        }
        // we don't validate Message-Authenticator, as it is optional in RFC5997
        // and we have checked Response-Authenticaor.
        return AbstractTest::RETVAL_OK;
    }
}