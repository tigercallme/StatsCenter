<?php
namespace App\Controller;

use Swoole;
use Swoole\Pager;

class App_host extends \App\LoginController
{
    private $project_id_regex = '/[a-zA-Z\d_]+/';
    private $env_id_regex = '/[a-zA-Z\d_]+/';
    private $url_regex = '/^https?:\/\//';

    function add_host()
    {
        if (empty($_POST))
        {
            $this->display_edit_host_page();
        }
        else
        {
            $this->edit_host_check($project_id, $env_id, $host, $error);
            $host = rtrim($host, '/');
            if (!empty($error))
            {
                return $this->display_edit_host_page($project_id, $env_id, $host, $error);
            }

            $identifier = strtolower($project_id . '-' . $env_id);

            Swoole::$php->redis('cluster')->hSet('app-host:id-host-map', $identifier, $host);
            $max_score_list =  Swoole::$php->redis('cluster')->zRevRangeByScore(
                'app-host:host-list',
                '+inf',
                '-inf',
                array(
                    'limit' => array(0, 1),
                    'withscores' => true,
                )
            );
            $max_score = !empty($max_score_list) ? reset($max_score_list) : 0;
            Swoole::$php->redis('cluster')->zAdd('app-host:host-list', $max_score + 1, $identifier);

            return Swoole\JS::js_goto('添加成功！', '/app_host/host_list');
        }
    }

    function edit_host()
    {
        $id = trim($this->value($_GET, 'id'));
        $pieces = explode('-', $id);

        if ($id === '' || count($pieces) !== 2)
        {
            $this->http->status(404);
            return;
        }

        $host = Swoole::$php->redis('cluster')->hGet('app-host:id-host-map', $id);
        list($project_id, $env_id) = explode('-', $id);

        if (empty($_POST))
        {
            return $this->display_edit_host_page($project_id, $env_id, $host);
        }
        else
        {
            $this->edit_host_check($project_id, $env_id, $host, $error);
            if (!empty($error))
            {
                return $this->display_edit_host_page($project_id, $env_id, $host, $error);
            }

            Swoole::$php->redis('cluster')->hSet('app-host:id-host-map', $id, $host);

            return Swoole\JS::js_goto('编辑成功', '/app_host/host_list');
        }

    }

    private function edit_host_check(&$project_id, &$env_id, &$host, &$error)
    {
        $project_id = trim($this->value($_POST, 'project_id'));
        $env_id = trim($this->value($_POST, 'env_id'));
        $host = trim($this->value($_POST, 'host'));

        $error = '';
        if (!preg_match($this->project_id_regex, $project_id))
        {
            $error = '项目标识符格式不正确！';
        }
        elseif (!preg_match($this->env_id_regex, $env_id))
        {
            $error = '环境标识符格式不正确！';
        }
        elseif (!preg_match($this->url_regex, $host))
        {
            $error = '接口地址格式不正确！';
        }
    }

    private function display_edit_host_page($project_id = null, $env_id = null, $host = null, $error = null)
    {
        if ($this->env['mvc']['view'] === 'edit_host')
        {
            $form['project_id'] = Swoole\Form::input('project_id', $project_id, array('readonly' => 'readonly', 'style' => 'background-color:#eee;cursor:not-allowed'));
            $form['env_id'] = Swoole\Form::input('env_id', $env_id, array('readonly' => 'readonly', 'style' => 'background-color:#eee;cursor:not-allowed'));
        }
        else
        {
            $form['project_id'] = Swoole\Form::input('project_id', $project_id);
            $form['env_id'] = Swoole\Form::input('env_id', $env_id);
        }

        $form['host'] = Swoole\Form::input('host', $host);

        $this->assign('error', $error);
        $this->assign('form', $form);
        $this->display('app_host/edit_host.php');
    }

    function delete_host()
    {
        $id = trim($this->value($_GET, 'id'));
        if ($id !== '')
        {
            if (Swoole::$php->redis('cluster')->zRem('app-host:host-list', $id))
            {
                Swoole::$php->redis('cluster')->hDel('app-host:id-host-map', $id);
            }
        }
        return Swoole\JS::js_goto('删除成功！', '/app_host/host_list');
    }

    function host_list()
    {
        $page = $this->value($_GET, 'page', 1, true);

        $per_page = 10;
        $start = ($page - 1) * $per_page;
        $end = $start + $per_page - 1;

        $host_list = Swoole::$php->redis('cluster')->zRevRange('app-host:host-list', $start, $end);
        if (!empty($host_list))
        {
            $id_host_map = Swoole::$php->redis('cluster')->hMGet('app-host:id-host-map', $host_list);
            $id_host_map = $id_host_map ? $id_host_map : array();
        }
        else
        {
            $id_host_map = array();
        }

        // 分页
        $total = Swoole::$php->redis('cluster')->zCard('app-host:host-list');
        $pager = new Pager(array(
            'total' => $total,
            'perpage' => $per_page,
            'nowindex' => $page,
        ));

        $this->assign('id_host_map', $id_host_map);
        $this->assign('pager', array('render' => $pager->render()));
        $this->display();
    }

    function add_rule()
    {
        $type = trim($this->value($_REQUEST, 'type', 'uid'));

        if (empty($_POST))
        {
            return $this->display_edit_rule_page($type);
        }
        else
        {
            $this->edit_rule_check($type, $uid, $openudid, $project_id, $env_id, $error);
            if (!empty($error))
            {
                return $this->display_edit_rule_page($type, $uid, $openudid, $project_id, $env_id, $error);
            }

            if ($type === 'uid' && !empty($uid))
            {
                $key = "uid:{$uid}-{$project_id}";
            }
            elseif ($type === 'openudid' && !empty($openudid))
            {
                $key = "openudid:{$openudid}-{$project_id}";
            }
            else
            {
                return Swoole\JS::js_goto('系统繁忙', '/app_host/rule_list');
            }

            Swoole::$php->redis('cluster')->hSet('app-host:assign', $key, $env_id);
            $max_score_list = Swoole::$php->redis('cluster')->zRevRangeByScore(
                'app-host:assign-list',
                '+inf',
                '-inf',
                array(
                    'limit' => array(0, 1),
                    'withscores' => true,
                )
            );
            $max_score = !empty($max_score_list) ? reset($max_score_list) : 0;
            Swoole::$php->redis('cluster')->zAdd('app-host:assign-list', $max_score + 1, $key);

            return Swoole\JS::js_goto('添加成功！', '/app_host/rule_list');
        }
    }

    public function edit_rule()
    {
        $id = trim($this->value($_GET, 'id'));
        $pieces = preg_split('/:|-/', $id);

        if ($id === '' || count($pieces) !== 3)
        {
            $this->http->status(404);
            return;
        }

        $type = $pieces[0];
        $uid = $type === 'uid' ? $pieces[1] : null;
        $openudid = $type === 'openudid' ? $pieces[1] : null;
        $project_id = $pieces[2];
        $env_id = Swoole::$php->redis('cluster')->hGet('app-host:assign', $id);

        if (empty($_POST))
        {
            return $this->display_edit_rule_page($type, $uid, $openudid, $project_id, $env_id);
        }
        else
        {
            $this->edit_rule_check($type, $uid, $openudid, $project_id, $env_id, $error);
            if (!empty($error))
            {
                return $this->display_edit_rule_page($type, $uid, $openudid, $project_id, $env_id, $error);
            }

            Swoole::$php->redis('cluster')->hSet('app-host:assign', $id, $env_id);

            return Swoole\JS::js_goto('編輯成功', '/app_host/rule_list');
        }
    }

    public function delete_rule()
    {
        $id = trim($this->value($_GET, 'id'));
        if ($id !== '')
        {
            if (Swoole::$php->redis('cluster')->zRem('app-host:assign-list', $id))
            {
                Swoole::$php->redis('cluster')->hDel('app-host:assign', $id);
            }
        }

        return Swoole\JS::js_goto('删除成功！', '/app_host/rule_list');
    }

    private function display_edit_rule_page($type = null, $uid = null, $openudid = null, $project_id = null, $env_id = null, $error = null)
    {
        $view = $this->env['mvc']['view'];

        if ($view === 'edit_rule')
        {
            $attr_list = array('readonly' => 'readonly', 'style' => 'background-color:#eee;cursor:not-allowed');

            $form['uid'] = Swoole\Form::input('uid', $uid, $attr_list);
            $form['openudid'] = Swoole\Form::input('openudid', $openudid, $attr_list);
            $form['project_id'] = Swoole\Form::input('project_id', $project_id, $attr_list);
        }
        else
        {
            $form['uid'] = Swoole\Form::input('uid', $uid);
            $form['openudid'] = Swoole\Form::input('openudid', $openudid);
            $form['project_id'] = Swoole\Form::input('project_id', $project_id);
        }

        $form['env_id'] = Swoole\Form::input('env_id', $env_id);

        $this->assign('view', $view);
        $this->assign('error', $error);
        $this->assign('type', $type);
        $this->assign('form', $form);
        $this->display('app_host/edit_rule.php');
    }

    private function edit_rule_check(&$type, &$uid, &$openudid, &$project_id, &$env_id, &$error)
    {
        $uid = trim($this->value($_POST, 'uid'));
        $openudid = trim($this->value($_POST, 'openudid'));
        $project_id = trim($this->value($_POST, 'project_id'));
        $project_id = trim($this->value($_POST, 'project_id'));
        $env_id = trim($this->value($_POST, 'env_id'));

        $error = '';

        if ($type === 'uid' && empty($uid))
        {
            $error = 'UID是必需的！';
        }
        elseif ($type === 'openudid' && empty($openudid))
        {
            $error = 'OpenUDID是必需的！';
        }
        elseif (!preg_match($this->project_id_regex, $project_id))
        {
            $error = '项目标识符格式不正确！';
        }
        elseif (!preg_match($this->env_id_regex, $env_id))
        {
            $error = '环境标识符格式不正确！';
        }
    }

    function rule_list()
    {
        $page = $this->value($_GET, 'page', 1, true);

        $per_page = 10;
        $start = ($page - 1) * $per_page;
        $end = $page + $per_page - 1;

        $assign_list = Swoole::$php->redis('cluster')->zRevRange('app-host:assign-list', $start, $end);
        if (!empty($assign_list))
        {
            $rules = Swoole::$php->redis('cluster')->hMGet('app-host:assign', $assign_list);
            $rules = $rules ? $rules : array();
        }
        else
        {
            $rules = array();
        }

        $data = array();
        if (!empty($rules))
        {
            $host_id_list = array();
            foreach ($rules as $key => $env_id)
            {
                $pieces = explode('-', $key);

                if (count($pieces) !== 2)
                {
                    continue;
                }

                $project_id = $pieces[1];
                $host_id = "{$project_id}-{$env_id}";
                $host_id_list[] = $host_id;
                $data[$pieces[0]] = array(
                    'host_id' => $host_id,
                    'project_id' => $project_id,
                );
            }
        }

        if (!empty($host_id_list))
        {
            $id_host_map = Swoole::$php->redis('cluster')->hMGet('app-host:id-host-map', $host_id_list);

            foreach ($data as &$row)
            {
                $row['host'] = !empty($id_host_map[$row['host_id']]) ? $id_host_map[$row['host_id']] : '无';
            }
            unset($row);
        }

        $total = Swoole::$php->redis('cluster')->zCard('app-host:assign-list');
        $pager = new Pager(array(
            'total' => $total,
            'perpage' => $per_page,
            'nowindex' => $page,
        ));

        $this->assign('data', $data);
        $this->assign('pager', array('render' => $pager->render()));
        $this->display();
    }
}