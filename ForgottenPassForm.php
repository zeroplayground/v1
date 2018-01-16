<?php

namespace common\models;

use Yii;
use yii\base\Model;
use frontend\api\Biztositok;
use frontend\lib\Lib;

/**
 * Login form
 */
class ForgottenPassForm extends Model
{

    public $email;
    public $rememberMe = true;
    private $_user;

    /**
     * @inheritdoc
     */
    public function rules()
    {
        return [
            // username and password are both required
            [['email'], 'required' , "message" => "A mező kitöltése kötelező"],
            ['email', 'email', "message" => "Hibás e-mail cím!"],
        ];
    }

    public function validate($attributeNames = null, $clearErrors = true)
    {
        if (parent::validate($attributeNames, $clearErrors)) {

            try {
                $response = Biztositok::callApi('user/password-reset/token', [
                            'email' => $this->email,
                ]);
            } catch (\frontend\api\BiztositokAPIException $e) {
                $errors = Biztositok::getLastApiResponse()->getErrors();
                foreach ($errors as $error) {
                    $this->addError($error["field"], $error['error_message']);
                }
                return false;
            } catch (\yii\base\Exception $e) {
                $this->addError("email", "Rendszerhiba!");
                return false;
            }


            return true;
        }

        return false;
    }

    public function save()
    {
        $response = Biztositok::getLastApiResponse();
        \Yii::info("last response:");
        \Yii::info($response);

        $pageID = Lib::getPageID();
        
        $token = new ForgottenPassword();
        $token->email = $this->email;
        $token->api_token = $response->get('token');
        $token->expiration_time = $response->get('expiration');
        $token->hash = md5($this->email . time());
        $token->page_id = $pageID;
        $token->save();
        
        switch ($pageID)
        {
            case Lib::PAGE_ID_UTAS:
                $mailer = \Yii::$app->mailer;
                break;
            case Lib::PAGE_ID_SI:
                $mailer = \Yii::$app->mailer2;
                break;
        }
        $receiver = (YII_ENV == "local") ? "kovacs.lorant@wwdh.hu" : $this->email;
        $mailer->compose([
                    'html' => 'forgottenPass-html',
                    'text' => 'forgottenPass-text'
                        ], [//becserélendő cuccok
                    'expirationTime' => $token->expiration_time,
                    'link' => \Yii::$app->urlManager->createAbsoluteUrl([
                            "site/addnewpassword", "hash" => $token->hash
                            ])
                ])
                ->setTo($receiver)
                ->setFrom(["noreply@".Lib::getLiveDomainURL() => Lib::getPageNameToPrint(true)])
                ->setSubject(Lib::getPageNameToPrint(true) . " - Elfelejtett jelszó")
                ->send();

        return true;
    }

    /**
     * Finds user by [[username]]
     *
     * @return User|null
     */
    protected function getUser()
    {
        if ($this->_user === null) {
            $this->_user = User::findByUsername("demo");
        }

        return $this->_user;
    }

}
