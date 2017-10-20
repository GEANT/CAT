<?php

namespace web\lib\admin\domain;

use core\ProfileSilverbullet;

/**
 * 
 * @author Zilvinas Vaira
 *
 */
class SilverbulletCertificate extends PersistentEntity {

    const TABLE = 'silverbullet_certificate';

    const COLUMN_NAME_LIST = "`id`, `profile_id`, `silverbullet_user_id`, `silverbullet_invitation_id`, `serial_number`, `cn`, `issued`, `expiry`, `device`, `revocation_status`, `revocation_time`, `OCSP`, `OCSP_timestamp`";
    
    /**
     * Required profile identifier
     * 
     * @var string
     */
    const PROFILEID = 'profile_id';

    /**
     * Required user identifier
     * 
     * @var string
     */
    const SILVERBULLETUSERID = 'silverbullet_user_id';
    
    /**
     * Required invitation identifier
     *
     * @var string
     */
    const SILVERBULLETINVITATIONID = 'silverbullet_invitation_id';

    /**
     * 
     * @var string
     */
    const SERIALNUMBER = 'serial_number';

    /**
     *
     * @var string
     */
    const CN = 'cn';

    /**
     *
     * @var string
     */
    const EXPIRY = 'expiry';

    /**
     *
     * @var string
     */
    const ISSUED = 'issued';
    
    /**
     *
     * @var string
     */
    const DEVICE = 'device';

    /**
     *
     * @var string
     */
    const REVOCATION_STATUS = 'revocation_status';

    /**
     *
     * @var string
     */
    const REVOCATION_TIME = 'revocation_time';

    /**
     * 
     * @var string
     */
    const NOT_REVOKED = 'NOT_REVOKED';

    /**
     * 
     * @var string
     */
    const REVOKED = 'REVOKED';

    /**
     * 
     * @param $silverbulletInvitation SilverbulletInvitation
     */
    public function __construct($silverbulletInvitation) {
        parent::__construct(self::TABLE, self::TYPE_INST);
        $this->setAttributeType(self::PROFILEID, Attribute::TYPE_INTEGER);
        $this->setAttributeType(self::SILVERBULLETUSERID, Attribute::TYPE_INTEGER);
        $this->setAttributeType(self::SILVERBULLETINVITATIONID, Attribute::TYPE_INTEGER);
        if (!empty($silverbulletInvitation)) {
            $this->set(self::PROFILEID, $silverbulletInvitation->get(SilverbulletCertificate::PROFILEID));
            $this->set(self::SILVERBULLETUSERID, $silverbulletInvitation->get(SilverbulletCertificate::SILVERBULLETUSERID));
            $this->set(self::SILVERBULLETINVITATIONID, $silverbulletInvitation->getIdentifier());
            $this->set(self::EXPIRY, date('Y-m-d H:i:s', strtotime("+1 week")));
        }
    }
    
    /**
     * 
     * {@inheritDoc}
     * @see \web\lib\admin\domain\PersistentInterface::validate()
     */
    public function validate(){
        return (
            !empty($this->get(self::PROFILEID)) &&
            !empty($this->get(self::SILVERBULLETUSERID)) &&
            !empty($this->get(self::SILVERBULLETINVITATIONID))
        );
    }

    /**
     * 
     * @param string $serialNumber
     * @param string $cn
     * @param string $expiry
     */
    public function setCertificateDetails($serialNumber, $cn, $expiry) {
        $this->set(self::SERIALNUMBER, $serialNumber);
        $this->set(self::CN, $cn);
        $expiry = date('Y-m-d H:i:s', strtotime($expiry));
        $this->set(self::EXPIRY, $expiry);
    }
    
    /**
     * 
     * @param string $deviceName
     */
    public function setDeviceName($deviceName) {
        $this->set(self::DEVICE, $deviceName);
    }

    /**
     *
     * @return string
     */
    public function getSerialNumber() {
        if (empty($this->get(self::SERIALNUMBER))) {
            return "n/a";
        } else {
            return $this->get(self::SERIALNUMBER);
        }
    }

    /**
     *
     * @return string
     */
    public function getCommonName() {
        if (empty($this->get(self::CN))) {
            return "n/a";
        } else {
            return $this->get(self::CN);
        }
    }

    /**
     *
     * @return string
     */
    public function getShortCommonName() {
        $commonName = $this->getCommonName();
        $delimiter = '@';
        $parts = explode($delimiter, $commonName);
        if (isset($parts[0])) {
            return $parts[0] . $delimiter . '…';
        } else {
            return '';
        }
    }

    /**
     * 
     * @param string $field
     * @return string
     */
    public function getDateString($field) {
        if (empty($this->get($field))) {
            return "n/a";
        } else {
            return date('Y-m-d', strtotime($this->get($field)));
        }
    }

    /**
     *
     * @return boolean
     */
    public function isExpired() {
        $expiryTime = strtotime($this->get(self::EXPIRY));
        $currentTime = time();
        return $currentTime > $expiryTime;
    }

    public function getCertificateDetails() {
        return _('Device:') . $this->get(self::DEVICE) . '<br> '
                . _('Serial Number:') . dechex($this->getSerialNumber()) . '<br> '
                . _('CN:') . $this->getShortCommonName() . '<br> '
                . _('Expiry:') . $this->getDateString(self::EXPIRY) . '<br>'
                . _('Issued:') . $this->getDateString(self::ISSUED);
    }

    /**
     * 
     * @return boolean
     */
    public function isGenerated() {
        return !empty($this->get(self::SERIALNUMBER)) && !empty($this->get(self::CN));
    }

    /**
     * 
     * @param boolean $isRevoked
     */
    public function setRevoked($isRevoked) {
        $this->set(self::REVOCATION_STATUS, $isRevoked ? self::REVOKED : self::NOT_REVOKED);
        $this->set(self::REVOCATION_TIME, date('Y-m-d H:i:s', strtotime("now")));
    }

    /**
     * 
     * @return boolean
     */
    public function isRevoked() {
        return $this->get(self::REVOCATION_STATUS) == self::REVOKED;
    }

    /**
     * Revokes invitation or revokes certificate and its actual instance 
     * 
     * @param ProfileSilverbullet $profile
     */
    public function revoke($profile) {
        if ($this->isGenerated()) {
            $profile->revokeCertificate($this->getSerialNumber());
        } else {
            $this->setRevoked(true);
            $this->save();
        }
    }

    /**
     * 
     * @param int $certificateId
     * @return \web\lib\admin\domain\SilverbulletCertificate
     */
    public static function prepare($certificateId) {
        $instance = new SilverbulletCertificate(null);
        $instance->set(self::ID, $certificateId);
        return $instance;
    }

    /**
     * 
     * @param SilverbulletUser $silverbulletUser
     * @param Attribute $searchAttribute
     * @return \web\lib\admin\domain\SilverbulletCertificate[]
     */
    public static function getList($silverbulletUser = null, $searchAttribute = null) {
        $databaseHandle = \core\DBConnection::handle(self::TYPE_INST);
        if ($searchAttribute != null && $silverbulletUser != null) {
            $userId = $silverbulletUser->getAttribute(self::ID);
            $userType = $userId->getType();
            $userValue = $userId->value;
            $attrType = $searchAttribute->getType();
            $attrValue = $searchAttribute->value;
            $query = sprintf("SELECT %s FROM `%s` WHERE `%s`=? AND `%s`=? ORDER BY `%s`, `%s` DESC", self::COLUMN_NAME_LIST, self::TABLE, self::SILVERBULLETUSERID, $searchAttribute->key, self::REVOCATION_STATUS, self::EXPIRY);
            $types = $userType . $attrType;
            $result = $databaseHandle->exec($query, $types, $userValue, $attrValue);
        } else if($silverbulletUser != null) {
            $userId = $silverbulletUser->getAttribute(self::ID);
            $userType = $userId->getType();
            $userValue = $userId->value;
            $query = sprintf("SELECT %s FROM `%s` WHERE `%s`=? ORDER BY `%s`, `%s` DESC", self::COLUMN_NAME_LIST, self::TABLE, self::SILVERBULLETUSERID, self::REVOCATION_STATUS, self::EXPIRY);
            $result = $databaseHandle->exec($query, $userType, $userValue);
        } else if ($searchAttribute != null) {
            $attrType = $searchAttribute->getType();
            $attrValue = $searchAttribute->value;
            $query = sprintf("SELECT %s FROM `%s` WHERE `%s`=? ORDER BY `%s`, `%s` DESC", self::COLUMN_NAME_LIST, self::TABLE, $searchAttribute->key, self::REVOCATION_STATUS, self::EXPIRY);
            $result = $databaseHandle->exec($query, $attrType, $attrValue);
        } else {
            $query = sprintf("SELECT %s FROM `%s` ORDER BY `%s`, `%s` DESC", self::COLUMN_NAME_LIST, self::TABLE, self::REVOCATION_STATUS, self::EXPIRY);
            $result = $databaseHandle->exec($query);
        }
        
        $list = array();
        while ($row = mysqli_fetch_assoc($result)) {
            $certificate = new SilverbulletCertificate(null);
            $certificate->setRow($row);
            $list[] = $certificate;
        }
        return $list;
    }

}
