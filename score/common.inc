<?php
/**
 * 特殊文字をHTMLエンティティに変換する
 *
 * htmlspecialcharsのラッパー
 *
 * @param string $str 変換元の文字列
 * @return string 変換後の文字列
 */
function h($str)
{
    return htmlspecialchars($str, ENT_QUOTES|ENT_HTML5, "UTF-8");
}
