<?php
/**
* 2007-2019 PrestaShop
*
* NOTICE OF LICENSE
*
* This source file is subject to the Academic Free License (AFL 3.0)
* that is bundled with this package in the file LICENSE.txt.
* It is also available through the world-wide-web at this URL:
* http://opensource.org/licenses/afl-3.0.php
* If you did not receive a copy of the license and are unable to
* obtain it through the world-wide-web, please send an email
* to license@prestashop.com so we can send you a copy immediately.
*
* DISCLAIMER
*
* Do not edit or add to this file if you wish to upgrade PrestaShop to newer
* versions in the future. If you wish to customize PrestaShop for your
* needs please refer to http://www.prestashop.com for more information.
*
*  @author    PrestaShop SA <contact@prestashop.com>
*  @copyright 2007-2019 PrestaShop SA
*  @license   http://opensource.org/licenses/afl-3.0.php  Academic Free License (AFL 3.0)
*  International Registered Trademark & Property of PrestaShop SA
*/
use PrestaShop\Module\PrestashopCheckout\Store\StoreManager;
use PrestaShop\Module\PrestashopCheckout\Api\Payment\Onboarding;
use PrestaShop\Module\PrestashopCheckout\Api\Psx\Onboarding as PsxOnboarding;
use PrestaShop\Module\PrestashopCheckout\Api\Firebase\Auth;
use PrestaShop\Module\PrestashopCheckout\PsxData\PsxDataValidation;

class AdminAjaxPrestashopCheckoutController extends ModuleAdminController
{
    public function ajaxProcessUnlinkPaypal()
    {
        (new StoreManager())->unlinkPaypal();
    }

    public function ajaxProcessUpdatePaymentMethodsOrder()
    {
        Configuration::updateValue('PS_CHECKOUT_PAYMENT_METHODS_ORDER', Tools::getValue('paymentMethods'));
    }

    public function ajaxProcessUpdateCaptureMode()
    {
        Configuration::updateValue('PS_CHECKOUT_INTENT', Tools::getValue('captureMode'));
    }

    public function ajaxProcessUpdatePaymentMode()
    {
        Configuration::updateValue('PS_CHECKOUT_MODE', Tools::getValue('paymentMode'));
    }

    /**
     * Logout firebase account
     */
    public function ajaxProcessLogOut()
    {
        $storeManager = new StoreManager();

        $storeManager->unlinkPaypal();
        $storeManager->psxLogout();
        $storeManager->updatePsxAccount('', '', '', '');

        $this->ajaxDie(json_encode(true));
    }

    /**
     * SignIn firebase account
     */
    public function ajaxProcessSignIn()
    {
        $email = Tools::getValue('email');
        $password = Tools::getValue('password');

        $firebase = new Auth();
        $response = $firebase->signInWithEmailAndPassword($email, $password);

        // if there is no error, save the account tokens in database
        if (!isset($response['error'])) {
            $storeManager = new StoreManager();
            $storeManager->updatePsxAccount(
                $response['idToken'],
                $response['refreshToken'],
                $response['localId'],
                $response['email']
            );
        }

        $this->ajaxDie(json_encode($response));
    }

    /**
     * SignUp firebase account
     */
    public function ajaxProcessSignUp()
    {
        $email = Tools::getValue('email');
        $password = Tools::getValue('password');

        $firebase = new Auth();
        $response = $firebase->signUpWithEmailAndPassword($email, $password);

        // if there is no error, save the account tokens in database
        if (!isset($response['error'])) {
            $storeManager = new StoreManager();
            $storeManager->updatePsxAccount(
                $response['idToken'],
                $response['refreshToken'],
                $response['localId'],
                $response['email']
            );
        }

        $this->ajaxDie(json_encode($response));
    }

    /**
     * Send email to reset firebase password
     */
    public function ajaxProcessSendPasswordResetEmail()
    {
        $email = Tools::getValue('email');

        $firebase = new Auth();
        $response = $firebase->sendPasswordResetEmail($email);

        $this->ajaxDie(json_encode($response));
    }

    /**
     * Get the form Payload for PSX. Check the data and send it to PSL
     */
    public function ajaxProcessPsxSendData()
    {
        $payload = json_decode(\Tools::getValue('payload'), true);
        $errors = (new PsxDataValidation())->validateData($payload);

        if (!empty($errors)) {
            $this->ajaxDie(json_encode($errors));
        }

        // Save form in database
        if (false === $this->savePsxForm($payload)) {
            $this->ajaxDie(json_encode(false));
        }

        $response = (new PsxOnboarding())->setOnboardingMerchant(array_filter($payload));

        if ($response) {
            $this->ajaxDie(json_encode(true));
        }

        $this->ajaxDie(json_encode(false));
    }

    private function savePsxForm($form)
    {
        return Configuration::updateValue('PS_CHECKOUT_PSX_FORM', json_encode($form));
    }

    public function ajaxProcessGetOnboardingLink()
    {
        $language = \Language::getLanguage($this->context->employee->id_lang);
        $locale = $language['locale'];

        // Generate a new onboarding link to lin a new merchant
        $this->ajaxDie(
            json_encode((new Onboarding($this->context->link))->getOnboardingLink($locale))
        );
    }

    // TODO: replace save action by StoreManager.php class
    private function saveFirebaseAccountIfNoErrors($user)
    {
        if (false === isset($user['error'])) {
            Configuration::updateValue('PS_PSX_FIREBASE_EMAIL', $user['email']);
            Configuration::updateValue('PS_PSX_FIREBASE_ID_TOKEN', $user['idToken']);
            Configuration::updateValue('PS_PSX_FIREBASE_LOCAL_ID', $user['localId']);
            Configuration::updateValue('PS_PSX_FIREBASE_REFRESH_TOKEN', $user['refreshToken']);
        }
    }
}
