#!/bin/sh
#
# スコアサーバに必要なファイル群をWebサーバホストにアップロードする
#
# 使い方の例:
#   scoreディレクトリにおいて、
#   $ sh tools/upload.sh ./ shell.osdn.jp:/home/**/score/
#

eval rsync -amvz --exclude=FeedWriter --include=*.php --include=*.js --include=*.inc --include=.htaccess --include=*.gif --include=*.css --include=*.py --include=*/ --exclude=* $1 $2
