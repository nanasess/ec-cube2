<?php
/*
 * This file is part of EC-CUBE
 *
 * Copyright(c) EC-CUBE CO.,LTD. All Rights Reserved.
 *
 * http://www.ec-cube.co.jp/
 *
 * This program is free software; you can redistribute it and/or
 * modify it under the terms of the GNU General Public License
 * as published by the Free Software Foundation; either version 2
 * of the License, or (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA  02111-1307, USA.
 */


/**
 * 会員登録のページクラス.
 *
 * @package Page
 * @author EC-CUBE CO.,LTD.
 * @version $Id$
 */
class LC_Page_Entry extends LC_Page_Ex
{
    /**
     * Page を初期化する.
     * @return void
     */
    public function init()
    {
        parent::init();
        $masterData         = new SC_DB_MasterData_Ex();
        $this->arrPref      = $masterData->getMasterData('mtb_pref');
        $this->arrJob       = $masterData->getMasterData('mtb_job');
        $this->arrReminder  = $masterData->getMasterData('mtb_reminder');
        $this->arrCountry   = $masterData->getMasterData('mtb_country');
        $this->arrSex       = $masterData->getMasterData('mtb_sex');
        $this->arrMAILMAGATYPE = $masterData->getMasterData('mtb_mail_magazine_type');

        // 生年月日選択肢の取得
        $objDate            = new SC_Date_Ex(BIRTH_YEAR, date('Y'));
        $this->arrYear      = $objDate->getYear('', START_BIRTH_YEAR, '');
        $this->arrMonth     = $objDate->getMonth(true);
        $this->arrDay       = $objDate->getDay(true);

        $this->httpCacheControl('nocache');
    }

    /**
     * Page のプロセス.
     *
     * @return void
     */
    public function process()
    {
        parent::process();
        $this->action();
        $this->sendResponse();
    }

    /**
     * Page のプロセス
     * @return void
     */
    public function action()
    {
        //決済処理中ステータスのロールバック
        $objPurchase = new SC_Helper_Purchase_Ex();
        $objPurchase->cancelPendingOrder(PENDING_ORDER_CANCEL_FLAG);

        $objFormParam = new SC_FormParam_Ex();

        // PC時は規約ページからの遷移でなければエラー画面へ遷移する
        if ($this->lfCheckReferer() === false) {
            SC_Utils_Ex::sfDispSiteError(PAGE_ERROR, '', true);
        }

        SC_Helper_Customer_Ex::sfCustomerEntryParam($objFormParam);
        $objFormParam->setParam($_POST);

        // mobile用（戻るボタンでの遷移かどうかを判定）
        if (!empty($_POST['return'])) {
            $_REQUEST['mode'] = 'return';
        }

        switch ($this->getMode()) {
            case 'confirm':
                if (isset($_POST['submit_address'])) {
                    // 入力エラーチェック
                    $this->arrErr = $this->lfCheckError($_POST);
                    // 入力エラーの場合は終了
                    if (count($this->arrErr) == 0) {
                        // 郵便番号検索文作成
                        $zipcode = $_POST['zip01'] . $_POST['zip02'];

                        // 郵便番号検索
                        $arrAdsList = SC_Utils_Ex::sfGetAddress($zipcode);

                        // 郵便番号が発見された場合
                        if (!empty($arrAdsList)) {
                            $data['pref'] = $arrAdsList[0]['state'];
                            $data['addr01'] = $arrAdsList[0]['city']. $arrAdsList[0]['town'];
                            $objFormParam->setParam($data);

                            // 該当無し
                        } else {
                            $this->arrErr['zip01'] = '※該当する住所が見つかりませんでした。<br>';
                        }
                    }
                    break;
                }

                //-- 確認
                $this->arrErr = SC_Helper_Customer_Ex::sfCustomerEntryErrorCheck($objFormParam);
                // 入力エラーなし
                if (empty($this->arrErr)) {
                    //パスワード表示
                    $this->passlen      = SC_Utils_Ex::sfPassLen(strlen($objFormParam->getValue('password')));

                    $this->tpl_mainpage = 'entry/confirm.tpl';
                    $this->tpl_title    = '会員登録(確認ページ)';
                }
                break;
            case 'complete':
                //-- 会員登録と完了画面
                $this->arrErr = SC_Helper_Customer_Ex::sfCustomerEntryErrorCheck($objFormParam);
                if (empty($this->arrErr)) {
                    $uniqid             = $this->lfRegistCustomerData($this->lfMakeSqlVal($objFormParam));

                    $this->lfSendMail($uniqid, $objFormParam->getHashArray());

                    // 仮会員が無効の場合
                    if (CUSTOMER_CONFIRM_MAIL == false) {
                        // ログイン状態にする
                        $objCustomer = new SC_Customer_Ex();
                        $objCustomer->setLogin($objFormParam->getValue('email'));
                    }

                    // 完了ページに移動させる。
                    $_SESSION['registered_customer_id'] = SC_Helper_Customer_Ex::sfGetCustomerId($uniqid);
                    SC_Response_Ex::sendRedirect('complete.php');
                }
                break;
            case 'return':
                // quiet.
                break;
            default:
                break;
        }
        $this->arrForm = $objFormParam->getFormParamList();
    }

    /**
     * 会員情報の登録
     *
     * @access private
     * @return uniqid
     */
    public function lfRegistCustomerData($sqlval)
    {
        SC_Helper_Customer_Ex::sfEditCustomerData($sqlval);

        return $sqlval['secret_key'];
    }

    /**
     * 会員登録に必要なSQLパラメーターの配列を生成する.
     *
     * フォームに入力された情報を元に, SQLパラメーターの配列を生成する.
     * モバイル端末の場合は, email を email_mobile にコピーし,
     * mobile_phone_id に携帯端末IDを格納する.
     *
     * @param SC_FormParam $objFormParam
     * @access private
     * @return $arrResults
     */
    public function lfMakeSqlVal(&$objFormParam)
    {
        $arrForm                = $objFormParam->getHashArray();
        $arrResults             = $objFormParam->getDbArray();

        // 生年月日の作成
        $arrResults['birth']    = SC_Utils_Ex::sfGetTimestamp($arrForm['year'], $arrForm['month'], $arrForm['day']);

        // 仮会員 1 本会員 2
        $arrResults['status']   = (CUSTOMER_CONFIRM_MAIL == true) ? '1' : '2';

        /*
         * secret_keyは、テーブルで重複許可されていない場合があるので、
         * 本会員登録では利用されないがセットしておく。
         */
        $arrResults['secret_key'] = SC_Helper_Customer_Ex::sfGetUniqSecretKey();

        // 入会時ポイント
        $CONF = SC_Helper_DB_Ex::sfGetBasisData();
        $arrResults['point'] = $CONF['welcome_point'];

        if (SC_Display_Ex::detectDevice() == DEVICE_TYPE_MOBILE) {
            // 携帯メールアドレス
            $arrResults['email_mobile']     = $arrResults['email'];
            // PHONE_IDを取り出す
            $arrResults['mobile_phone_id']  =  SC_MobileUserAgent_Ex::getId();
        }

        return $arrResults;
    }

    /**
     * 会員登録完了メール送信する
     *
     * @access private
     * @return void
     */
    public function lfSendMail($uniqid, $arrForm)
    {
        $objHelperMail = new SC_Helper_Mail_Ex();

        $objHelperMail->setPage($this);
        $resend_flg = true;
        $objHelperMail->sfSendRegistMail($uniqid, '', false, $resend_flg);
    }

    /**
     * kiyaku.php からの遷移の妥当性をチェックする
     *
     * 以下の内容をチェックし, 妥当であれば true を返す.
     * 1. 規約ページからの遷移かどうか
     * 2. PC及びスマートフォンかどうか
     * 3. 自分自身(会員登録ページ)からの遷移はOKとする
     *
     * @access protected
     * @return boolean kiyaku.php からの妥当な遷移であれば true
     */
    public function lfCheckReferer()
    {
        $arrRefererParseUrl = parse_url($_SERVER['HTTP_REFERER']);
        $referer_urlpath = $arrRefererParseUrl['path'];

        $kiyaku_urlpath = ROOT_URLPATH . 'entry/kiyaku.php';

        $arrEntryParseUrl = parse_url(ENTRY_URL);
        $entry_urlpath = $arrEntryParseUrl['path'];

        $allowed_urlpath = array(
            $kiyaku_urlpath,
            $entry_urlpath,
        );

        if (SC_Display_Ex::detectDevice() !== DEVICE_TYPE_MOBILE
            && !in_array($referer_urlpath, $allowed_urlpath)) {
            return false;
        }

        return true;
    }

    /**
     * 入力エラーのチェック.
     *
     * @param  array $arrRequest リクエスト値($_GET)
     * @return array $arrErr エラーメッセージ配列
     */
    public function lfCheckError($arrRequest)
    {
        // パラメーター管理クラス
        $objFormParam = new SC_FormParam_Ex();
        // パラメーター情報の初期化
        $objFormParam->addParam('郵便番号1', 'zip01', ZIP01_LEN, 'n', array('EXIST_CHECK', 'NUM_COUNT_CHECK', 'NUM_CHECK'));
        $objFormParam->addParam('郵便番号2', 'zip02', ZIP02_LEN, 'n', array('EXIST_CHECK', 'NUM_COUNT_CHECK', 'NUM_CHECK'));
        // // リクエスト値をセット
        $arrData['zip01'] = $arrRequest['zip01'];
        $arrData['zip02'] = $arrRequest['zip02'];
        $objFormParam->setParam($arrData);
        // エラーチェック
        $arrErr = $objFormParam->checkError();

        return $arrErr;
    }
}
