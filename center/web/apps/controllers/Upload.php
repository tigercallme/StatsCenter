<?php
namespace App\Controller;
use Swoole;

class Upload extends \App\LoginController
{
    function add()
    {
        if (empty($_POST))
        {
            $this->display('upload/edit.php');
        }
        else
        {
            if (!isset($_POST['url_list']) || !is_array($_POST['url_list']))
            {
                exit($this->json('', 1, 'Param url_list is needed.'));
            }
            $url_list = $_POST['url_list'];

            $res = model('Files')->add_if_not_exists($url_list);
            if ($res === false)
            {
                exit($this->json('', 1, 'Add files failed.'));
            }

            exit($this->json('', 0, 'Success'));
        }
    }

    function delete()
    {
        if (isset($_GET['id']))
        {
            $id = (int) $_GET['id'];
            $res = model('Files')->del($id);

            $msg = $res ? '操作成功' : '操作失败';
            \Swoole\JS::js_goto($msg, '/upload/file_list');
        }
        else
        {
            $this->http->status(302);
            $this->http->header('Location', '/upload/file_list');
        }
    }

    function file_list()
    {
        $data = array();

        $data = model('Files')->gets(
            array('where' => array('1 = 1'), 'order' => 'add_time DESC'),
            $pager
        );
        if ($data === false)
        {
            $data = array();
        }

        $this->assign('data', $data);
        $this->display('upload/file_list.php');
    }
}
