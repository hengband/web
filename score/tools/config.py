#!/usr/bin/python
# -*- coding: utf-8

import ConfigParser

config = {}


def parse(config_file):
    '''設定ファイルをパースする

    指定したINIファイル形式の設定ファイルをConfigParserでパースする。
    パースした結果は辞書型でグローバル空間のconfig変数にてアクセス可能とする。

    例)
    [Section1]
    foo = bar

    のような設定ファイルの場合、config['Section1']['foo'] = 'bar' となる

    Args:
        config_file: 設定ファイルのパスを表す文字列。
    '''
    ini = ConfigParser.ConfigParser()
    ini.read(config_file)

    global config
    config = {s: dict(ini.items(s)) for s in ini.sections()}
