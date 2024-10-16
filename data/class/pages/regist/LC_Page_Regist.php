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
class LC_Page_Regist extends LC_Page_Ex
{
    /**
     * Page を初期化する.
     *
     * @return void
     */
    public function init()
    {
        $this->skip_load_page_layout = true;
        parent::init();
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
     * Page のAction.
     *
     * @return void
     */
    public function action()
    {
        switch ($this->getMode()) {
            case 'regist':
            //--　本登録完了のためにメールから接続した場合
                //-- 入力チェック
                $this->arrErr       = $this->lfCheckError($_GET);
                if ($this->arrErr) SC_Utils_Ex::sfDispSiteError(FREE_ERROR_MSG, '', true, $this->arrErr['id']);

                $registSecretKey    = $this->lfRegistData($_GET);   //本会員登録（フラグ変更）
                $this->lfSendRegistMail($registSecretKey);          //本会員登録完了メール送信

                $_SESSION['registered_customer_id'] = SC_Helper_Customer_Ex::sfGetCustomerId($registSecretKey);
                SC_Response_Ex::sendRedirect('complete.php');
                break;
            //--　それ以外のアクセスは無効とする
            default:
                SC_Utils_Ex::sfDispSiteError(FREE_ERROR_MSG, '', true, '無効なアクセスです。');
                break;
        }

    }

    /**
     * 仮会員を本会員にUpdateする
     *
     * @param mixed $array
     * @access private
     * @return string $arrRegist['secret_key'] 本登録ID
     */
    public function lfRegistData($array)
    {
        $objQuery                   = SC_Query_Ex::getSingletonInstance();
        $arrRegist['secret_key']    = SC_Helper_Customer_Ex::sfGetUniqSecretKey(); //本登録ID発行
        $arrRegist['status']        = 2;
        $arrRegist['update_date']   = 'CURRENT_TIMESTAMP';

        $objQuery->begin();
        $objQuery->update('dtb_customer', $arrRegist, 'secret_key = ? AND status = 1', array($array['id']));
        $objQuery->commit();

        return $arrRegist['secret_key'];
    }

    /**
     * 入力エラーチェック
     *
     * @param mixed $array
     * @access private
     * @return array エラーの配列
     */
    public function lfCheckError($array)
    {
        $objErr = new SC_CheckError_Ex($array);

        if (preg_match("/^[[:alnum:]]+$/", $array['id'])) {
            if (!is_numeric(SC_Helper_Customer_Ex::sfGetCustomerId($array['id'], true))) {
                $objErr->arrErr['id'] = '※ 既に会員登録が完了しているか、無効なURLです。<br>';
            }

        } else {
            $objErr->arrErr['id'] = '無効なURLです。メールに記載されている本会員登録用URLを再度ご確認ください。';
        }

        return $objErr->arrErr;
    }

    /**
     * 正会員登録完了メール送信
     *
     * @param string $registSecretKey
     * @access private
     * @return void
     */
    public function lfSendRegistMail($registSecretKey)
    {
        $objHelperMail = new SC_Helper_Mail_Ex();

        $objHelperMail->setPage($this);
        $objHelperMail->sfSendRegistMail($registSecretKey);
    }
}
