<?php
/*
 *  Jirafeau, your web file repository
 *  Copyright (C) 2008  Julien "axolotl" BERNARD <axolotl@magieeternelle.org>
 *  Copyright (C) 2015  Jerome Jutteau <j.jutteau@gmail.com>
 *
 *  This program is free software: you can redistribute it and/or modify
 *  it under the terms of the GNU Affero General Public License as
 *  published by the Free Software Foundation, either version 3 of the
 *  License, or (at your option) any later version.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU Affero General Public License for more details.
 *
 *  You should have received a copy of the GNU Affero General Public License
 *  along with this program.  If not, see <http://www.gnu.org/licenses/>.
 */
define('JIRAFEAU_ROOT', dirname(__FILE__) . '/');

require(JIRAFEAU_ROOT . 'lib/settings.php');
require(JIRAFEAU_ROOT . 'lib/functions.php');
require(JIRAFEAU_ROOT . 'lib/lang.php');

if (!isset($_GET['h']) || empty($_GET['h'])) {
    header('Location: ./');
    exit;
}

/* Operations may take a long time.
 * Be sure PHP's safe mode is off.
 */
@set_time_limit(0);
/* Remove errors. */
@error_reporting(0);

$link_name = $_GET['h'];

if (!preg_match('/[0-9a-zA-Z_-]+$/', $link_name)) {
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div class="error"><p>' . t('FILE_404') . '</p></div>';
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

$link = jirafeau_get_link($link_name);
if (count($link) == 0) {
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div class="error"><p>' . t('FILE_404') .
    '</p></div>';
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

$delete_code = '';
if (isset($_GET['d']) && !empty($_GET['d']) &&  $_GET['d'] != '1') {
    $delete_code = $_GET['d'];
}

$crypt_key = '';
if (isset($_GET['k']) && !empty($_GET['k'])) {
    $crypt_key = $_GET['k'];
}

$do_download = false;
if (isset($_GET['d']) && $_GET['d'] == '1') {
    $do_download = true;
}

$do_preview = false;
if (isset($_GET['p']) && !empty($_GET['p'])) {
    $do_preview = true;
}

$p = s2p($link['md5']);
if (!file_exists(VAR_FILES . $p . $link['md5'])) {
    jirafeau_delete_link($link_name);
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div class="error"><p>'.t('FILE_NOT_AVAIL').
    '</p></div>';
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

if (!empty($delete_code) && $delete_code == $link['link_code']) {
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    if (isset($_POST['do_delete'])) {
        jirafeau_delete_link($link_name);
        echo '<div class="message"><p>'.t('FILE_DELETED').
            '</p></div>';
    } else { ?>
        <div>
        <form action="f.php" method="post" id="submit_delete_post" class="form login">
        <input type="hidden" name="do_delete" value=1/>
        <fieldset>
             <legend> <?php echo t('CONFIRM_DEL') ?> </legend>
             <table>
             <tr><td>
             <?php echo t('GONNA_DEL') . ' "' . jirafeau_escape($link['file_name']) . '" (' . jirafeau_human_size($link['file_size']) . ').' ?>
             </td></tr>
             <tr><td>
                <?php echo t('USING_SERVICE'). ' <a href="tos.php" target="_blank" rel="noopener noreferrer">' . t('TOS') . '</a>.' ?>
             </td></tr>
             <tr><td>
                <input type="submit" id="submit_delete"  value="<?php echo t('DELETE'); ?>"
                onclick="document.getElementById('submit_delete_post').action='<?php echo 'f.php?h=' . $link_name . '&amp;d=' . $delete_code . "';"; ?>
                document.getElementById('submit_delete').submit ();"/>
             </td></tr>
             </table>
         </fieldset></form></div><?php
    }
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

if ($link['time'] != JIRAFEAU_INFINITY && time() > $link['time']) {
    jirafeau_delete_link($link_name);
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div class="error"><p>'.
    t('FILE_EXPIRED') . ' ' .
    t('FILE_DELETED') .
    '</p></div>';
    require(JIRAFEAU_ROOT . 'lib/template/footer.php');
    exit;
}

if (empty($crypt_key) && $link['crypted']) {
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div class="error"><p>' . t('FILE_404') .
    '</p></div>';
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

$password_challenged = false;
if (!empty($link['key'])) {
    if (!isset($_POST['key'])) {
        require(JIRAFEAU_ROOT.'lib/template/header.php');
        echo '<div>' .
             '<form action="f.php" method="post" id="submit_post" class="form login">'; ?>
             <input type = "hidden" name = "jirafeau" value = "<?php echo JIRAFEAU_VERSION ?>"/><?php
        echo '<fieldset>' .
             '<legend>' . t('PSW_PROTEC') .
             '</legend><table><tr><td>' .
             t('GIMME_PSW') . ' : ' .
             '<input type = "password" name = "key" />' .
             '</td></tr>' .
             '<tr><td>' .
             t('USING_SERVICE'). ' <a href="tos.php" target="_blank" rel="noopener noreferrer">' . t('TOS') . '</a>.' .
             '</td></tr>';

        if ($link['onetime'] == 'O') {
            echo '<tr><td id="self_destruct">' .
                 t('AUTO_DESTRUCT') .
                 '</td></tr>';
        } ?><tr><td><input type="submit" id = "submit_download"  value="<?php echo t('DL'); ?>"
        onclick="document.getElementById('submit_post').action='<?php
        echo 'f.php?h=' . $link_name . '&amp;d=1';
        if (!empty($crypt_key)) {
            echo '&amp;k=' . urlencode($crypt_key);
        } ?>';
        document.getElementById('submit_download').submit ();"/><?php
        if ($cfg['preview'] && jirafeau_is_viewable($link['mime_type'])) {
            ?><input type="submit" id = "submit_preview"  value="<?php echo t('PREVIEW'); ?>"
            onclick="document.getElementById('submit_post').action='<?php
            echo 'f.php?h=' . $link_name . '&amp;p=1';
            if (!empty($crypt_key)) {
                echo '&amp;k=' . urlencode($crypt_key);
            } ?>';
            document.getElementById('submit_preview').submit ();"/><?php

        }
        echo '</td></tr></table></fieldset></form></div>';
        require(JIRAFEAU_ROOT.'lib/template/footer.php');
        exit;
    } else {
        if ($link['key'] == md5($_POST['key'])) {
            $password_challenged = true;
        } else {
            sleep(2);
            require(JIRAFEAU_ROOT.'lib/template/header.php');
            echo '<div class="error"><p>' . t('ACCESS_KO') .
            '</p></div>';
            require(JIRAFEAU_ROOT.'lib/template/footer.php');
            exit;
        }
    }
}

if (!$password_challenged && !$do_download && !$do_preview) {
    require(JIRAFEAU_ROOT.'lib/template/header.php');
    echo '<div>' .
             '<form action="f.php" method="post" id="submit_post" class="form download">'; ?>
             <input type = "hidden" name = "jirafeau" value = "<?php echo JIRAFEAU_VERSION ?>"/><?php
        echo '<fieldset><legend>' . jirafeau_escape($link['file_name']) . '</legend><table>' .
             '<tr><td>' .
             t('NOW_DOWNLOADING') . ' "' . jirafeau_escape($link['file_name']) . '" (' . jirafeau_human_size($link['file_size']) . ').' .
             '</td></tr>' .
             '<tr><td>' .
             t('USING_SERVICE'). ' <a href="tos.php" target="_blank" rel="noopener noreferrer">' . t('TOS') . '</a>.' .
             '</td></tr>';

    if ($link['onetime'] == 'O') {
        echo '<tr><td id="self_destruct">' .
                 t('AUTO_DESTRUCT') .
                 '</td></tr>';
    } ?>
        <tr><td><input type="submit" id = "submit_download"  value="<?php echo t('DL'); ?>"
        onclick="document.getElementById('submit_post').action='<?php
        echo 'f.php?h=' . $link_name . '&amp;d=1';
    if (!empty($crypt_key)) {
        echo '&amp;k=' . urlencode($crypt_key);
    } ?>';
        document.getElementById('submit_post').submit ();"/><?php

        if ($cfg['preview'] && jirafeau_is_viewable($link['mime_type'])) {
            ?><input type="submit" id = "submit_preview"  value="<?php echo t('PREVIEW'); ?>"
            onclick="document.getElementById('submit_post').action='<?php
        echo 'f.php?h=' . $link_name . '&amp;p=1';
            if (!empty($crypt_key)) {
                echo '&amp;k=' . urlencode($crypt_key);
            } ?>';
        document.getElementById('submit_post').submit ();"/><?php

        }
    echo '</td></tr>';
    echo '</table></fieldset></form></div>';
    require(JIRAFEAU_ROOT.'lib/template/footer.php');
    exit;
}

header('HTTP/1.0 200 OK');
header('Content-Length: ' . $link['file_size']);
if (!jirafeau_is_viewable($link['mime_type']) || !$cfg['preview'] || $do_download) {
    header('Content-Disposition: attachment; filename="' . $link['file_name'] . '"');
} else {
    header('Content-Disposition: filename="' . $link['file_name'] . '"');
}
header('Content-Type: ' . $link['mime_type']);
header('Content-MD5: ' . hex_to_base64($link['md5']));

/* Read encrypted file. */
if ($link['crypted']) {
    /* Init module */
    $m = mcrypt_module_open('rijndael-256', '', 'ofb', '');
    /* Extract key and iv. */
    $md5_key = md5($crypt_key);
    $iv = jirafeau_crypt_create_iv($md5_key, mcrypt_enc_get_iv_size($m));
    /* Init module. */
    mcrypt_generic_init($m, $md5_key, $iv);
    /* Decrypt file. */
    $r = fopen(VAR_FILES . $p . $link['md5'], 'r');
    while (!feof($r)) {
        $dec = mdecrypt_generic($m, fread($r, 1024));
        print $dec;
        ob_flush();
    }
    fclose($r);
    /* Cleanup. */
    mcrypt_generic_deinit($m);
    mcrypt_module_close($m);
}
/* Read file. */
else {
    $r = fopen(VAR_FILES . $p . $link['md5'], 'r');
    while (!feof($r)) {
        print fread($r, 1024);
        ob_flush();
    }
    fclose($r);
}

if ($link['onetime'] == 'O') {
    jirafeau_delete_link($link_name);
}
exit;

?>
