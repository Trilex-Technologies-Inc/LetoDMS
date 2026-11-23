<?php
namespace LetoDMS\WebDAV;

class Auth extends \Sabre\DAV\Auth\Backend\AbstractBasic
{
    private $context;

    public function __construct(Context $context)
    {
        $this->context = $context;
        $this->setRealm('LetoDMS WebDAV');
    }

    protected function validateUserPass($username, $password)
    {
        $ctx = $this->context;
        $ctx->user = null;
        $user = $ctx->dms->getUserByLogin($username);
        if (!$user || $user->isDisabled() || $user->isGuest()) return false;
        $hash = $user->getPwd();
        $valid = preg_match('/^[a-f0-9]{32}$/i', $hash)
            ? hash_equals(strtolower($hash), md5($password)) : password_verify($password, $hash);
        if (!$valid) {
            $failures = $user->addLoginFailure();
            if ($ctx->settings->_loginFailure > 0 && $failures >= $ctx->settings->_loginFailure) $user->setDisabled(true);
            return false;
        }
        $expires = $user->getPwdExpiration();
        $expiryTime = $expires ? strtotime($expires) : false;
        if (!$user->isAdmin() && $ctx->settings->_passwordExpiration > 0 && $expiryTime > 0 && $expiryTime <= time()) return false;
        if ($user->isAdmin() && $ctx->settings->_adminIP !== '' &&
            ($_SERVER['REMOTE_ADDR'] ?? '') !== $ctx->settings->_adminIP) return false;
        if (!$user->hasPermission('webdav.access')) return false;
        $user->clearLoginFailures();
        $ctx->user = $user;
        $ctx->dms->setUser($user);
        return true;
    }
}
