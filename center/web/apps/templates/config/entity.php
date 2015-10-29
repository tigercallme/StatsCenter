<!DOCTYPE html>
<html lang="en-us">
<head>
    <meta charset="utf-8">
    <title><?= Swoole::$php->config['common']['site_name'] ?></title>
    <meta name="description" content="">
    <meta name="author" content="">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <?php include __DIR__ . '/../include/css.php'; ?>
    <script src='<?= WEBROOT ?>/static/jsoneditor/dist/jsoneditor.js'></script>
    <link rel="stylesheet" href="<?= WEBROOT ?>/static/jsoneditor/dist/jsoneditor.css">
</head>
<body class="">
<header style="background: #E4E4E4;color: #22201F" id="header">
    <span><img style="vertical-align:top;padding: 8px" width="80"
               src="<?=Swoole::$php->config['common']['logo_url']?>"/></span>
    <span id="logo" style="margin-left: 0px"><strong
            style="font-size: 18px;"><?= Swoole::$php->config['common']['site_name'] ?></strong></span>
    <span style="float: right;padding: 15px 5px;font-weight: bolder">
        <span style="text-transform: none;">
                    <a style="text-decoration: none" href="/user/edit">用户：<?= $_COOKIE['username'] ?>
        </span>
        <span style="text-transform: none;padding: 15px 5px;">
                    <a style="text-decoration: none;font-weight: bolder" href="/page/logout/">退出</a>
        </span>
    </span>
</header>
<aside id="left-panel">
    <!--            --><?php //include __DIR__.'/../include/login_info.php'; ?>
    <?php include __DIR__ . '/../include/leftmenu.php'; ?>
    <span class="minifyme"> <i class="fa fa-arrow-circle-left hit"></i> </span>
</aside>
<!-- END NAVIGATION -->

<!-- MAIN PANEL -->
<div id="main" role="main">

    <!-- RIBBON -->
    <div id="ribbon">
        <!-- breadcrumb -->
        <ol class="breadcrumb">
            <li>Home</li>
            <li>内容管理</li>
        </ol>

    </div>

    <div id="content">
        <div class="row">
            <article class="col-sm-12 sortable-grid ui-sortable">
                <div class="jarviswidget jarviswidget-sortable" id="wid-id-0" data-widget-togglebutton="false"
                     data-widget-editbutton="false" data-widget-fullscreenbutton="false" data-widget-colorbutton="false"
                     data-widget-deletebutton="false" role="widget" style="width: 933px">
                    <header role="heading">
                        <ul class="nav nav-tabs pull-left in">
                            <li class="active">
                                <a><i class="fa fa-clock-o"></i>
                                    <?php if (!empty($keys[$_GET['key']])): ?><span
                                        class="hidden-mobile hidden-tablet"><?= $keys[$_GET['key']] ?></span>
                                    <?php endif; ?>
                                </a>
                            </li>
                        </ul>
                        <span class="jarviswidget-loader"><i class="fa fa-refresh fa-spin"></i></span>
                    </header>

                    <div role="content">
                        <div class="widget-body">
                            <div class="widget-body bg-color-white" style="height: 600px;" id="jsoneditor"></div>
                            <div class="widget-body bg-color-white">
                                <button class="btn btn-success" id='json_submit'>保存</button>
                                <span id="msg"></span>
                            </div>
                        </div>
                    </div>
                    <!-- end widget div -->
                </div>
                <div class="jarviswidget jarviswidget-color-blueDark jarviswidget-sortable" id="wid-id-1"
                     data-widget-editbutton="false" role="widget" style="">
                </div>
                <div class="jarviswidget jarviswidget-color-blueDark jarviswidget-sortable" id="wid-id-2"
                     data-widget-editbutton="false" role="widget" style="">
                </div>
                <div class="jarviswidget jarviswidget-color-blueDark jarviswidget-sortable" id="wid-id-3"
                     data-widget-editbutton="false" role="widget" style="">
                </div>
            </article>
        </div>
    </div>
</div>

<?php include dirname(__DIR__) . '/include/javascript.php'; ?>
<script>
    var container = document.getElementById('jsoneditor');
    var options = {
        mode: 'tree',
        modes: ['code', 'tree'], // allowed modes
        error: function (err) {
            alert(err.toString());
        }
    };
    var editor = new JSONEditor(container, options);
    editor.set(<?=$json?>);

    $('#json_submit').click(function () {
        $.post('/config/entity/?category=<?=$_GET['category']?>', {'json': JSON.stringify(editor.get())}, function (data) {
            if (data == 'ok') {
                $("#msg").html('操作成功');
            }
        });
    });
</script>
</body>
</html>