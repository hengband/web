#!/usr/bin/python
# -*- coding: utf-8
#
# OSDNのサーバでrequests_oauthlibを使う方法のメモ
#
# 1. 環境変数PYTHONUSERBASEでインストール先をWebコンテンツ上の任意のディレクトリに指定し、pipに--userオプションをつけてインストールを実行
#    以下は /home/groups/h/he/hengband/htdocs/score/local 以下にインストールする例
#
#    `$ PYTHONUSERBASE=/home/groups/h/he/hengband/htdocs/score/local pip install --user requests_oauthlib`
#
# 2. パスは通っているはずなのにシステムにインストールされているrequestsとurllib3が何故か読み込みに失敗するので、上でインストールしたディレクトリにコピーする
#
#    `$ cp -a /usr/lib/python2.7/dist-packages/requests /usr/lib/python2.7/dist-packages/urllib3 /home/groups/h/he/hengband/htdocs/score/local/lib/python2.7/site-packages`
#
# 3. sys.path.appendで上でインストールしたディレクトリにパスを通してからrequests_oauthlibをimportする
#
#    `import sys`
#    `sys.path.append('/home/groups/h/he/hengband/htdocs/score/local/lib/python2.7/site-packages')`
#    `import requests_oauthlib`
#

import sys
import ConfigParser
import sqlite3
import gzip
import re


def get_config(config_file):
    ini = ConfigParser.ConfigParser()
    ini.read(config_file)

    config = {s: {i[0]: i[1] for i in ini.items(s)}
              for s in ini.sections()}

    return config


def get_score_data(score_db_path, score_id):
    if score_id is None:
        cond = 'ORDER BY score_id DESC LIMIT 1'
    else:
        cond = 'WHERE score_id = :score_id'

    with sqlite3.connect(score_db_path) as con:
        con.row_factory = sqlite3.Row
        sql = '''
SELECT
  *,
  CASE
    WHEN realm_id IS NOT NULL THEN '(' || group_concat(realm_name) || ')'
    ELSE ''
  END AS realms_name,
  CASE
    WHEN killer = 'ripe' THEN '勝利の後引退'
    WHEN killer = 'Seppuku' THEN '勝利の後切腹'
    ELSE killer || 'に殺された'
  END AS death_reason
FROM
 (SELECT
    *
  FROM
    scores_view
  {cond})
NATURAL LEFT JOIN
  score_realms
NATURAL LEFT JOIN
  realms
GROUP BY
  score_id
'''.format(cond=cond)
        c = con.execute(sql, {'score_id': score_id})
        score = c.fetchall()

    return score[0] if len(score) == 1 else None


def get_death_reason_detail(score_id):
    '''
    ダンプファイル内から詳細な死因を取得する
    @param score_id ダンプファイルのスコアID
    @return 詳細な死因を表す文字列。ダンプファイルが無い、もしくは詳細な死因が見つからなかった場合None。
    '''
    subdir = (score_id // 1000) * 1000
    try:
        with gzip.open("dumps/{0}/{1}.txt.gz"
                       .format(subdir, score_id), 'r') as f:
            dump = f.readlines()
    except IOError:
        return None

    # NOTE: 死因の記述は31行目から始まる
    death_reason = unicode(''.join([l.strip() for l in dump[30:33]]), "UTF-8")
    match = re.search(u"…あなたは、?(.+)。", death_reason)

    return match.group(1) if match else None


def create_tweet(score_db, score_id):
    score_data = get_score_data(score_db, options.score_id)
    if score_data is None:
        return None

    death_reason_detail = get_death_reason_detail(score_id)
    if death_reason_detail is None:
        death_reason_detail = (u"{0} {1}階"
                               .format(score_data['death_reason'],
                                       score_data['depth']))

    summary = (u"【新着スコア】{personality_name}{name} Score:{score} "
               u"{race_name} {class_name}{realms_name} {death_reason_detail}"
               ).format(death_reason_detail=death_reason_detail, **score_data)

    dump_url = ("https://hengband.osdn.jp/score/show_dump.php?score_id={}"
                ).format(score_data['score_id'])
    screen_url = ("https://hengband.osdn.jp/score/show_screen.php?score_id={}"
                  ).format(score_data['score_id'])

    tweet = (u"{summary}\n\n"
             u"dump: {dump_url}\n"
             u"screen: {screen_url}\n"
             u"#hengband"
             ).format(summary=summary,
                      dump_url=dump_url,
                      screen_url=screen_url)
    return tweet


def tweet(oauth, tweet_contents):
    from requests_oauthlib import OAuth1Session
    from requests.adapters import HTTPAdapter
    from requests import codes
    from logging import getLogger
    logger = getLogger(__name__)

    twitter = OAuth1Session(**oauth)

    url = "https://api.twitter.com/1.1/statuses/update.json"

    params = {"status": tweet_contents}
    twitter.mount("https://", HTTPAdapter(max_retries=5))

    logger.info("Posting to Twitter...")
    logger.info(u"Tweet contents:\n{}".format(tweet_contents))
    res = twitter.post(url, params=params)

    if res.status_code == codes.ok:
        logger.info("Success.")
    else:
        logger.warning("Failed to post: {code}, {json}"
                       .format(code=res.status_code, json=res.json()))


def parse_option():
    from optparse import OptionParser
    parser = OptionParser()
    parser.add_option("-s", "--score-id",
                      type="int", dest="score_id",
                      help="Target score id.\n"
                           "If this option is not set, latest score is used.")
    parser.add_option("-c", "--config",
                      type="string", dest="config_file",
                      default="tweet_score.cfg",
                      help="Configuration INI file [default: %default]")
    parser.add_option("-l", "--log-file",
                      type="string", dest="log_file",
                      help="Logging file name")
    parser.add_option("-n", "--dry-run",
                      action="store_true", dest="dry_run",
                      default=False,
                      help="Output to stdout instead of posting to Twitter.")
    return parser.parse_args()


def setup_logger(log_file):
    from logging import getLogger, StreamHandler, FileHandler, Formatter, INFO
    logger = getLogger(__name__)
    logger.setLevel(INFO)
    sh = StreamHandler()
    logger.addHandler(sh)
    if log_file:
        formatter = Formatter('[%(asctime)s] %(message)s')
        fh = FileHandler(log_file)
        fh.setFormatter(formatter)
        logger.addHandler(fh)


if __name__ == '__main__':
    (options, arg) = parse_option()
    setup_logger(options.log_file)
    from logging import getLogger
    logger = getLogger(__name__)

    try:
        config = get_config(options.config_file)
        if 'Python' in config:
            sys.path.append(config['Python']['local_lib_path'])

        tweet_contents = create_tweet(config['ScoreDB']['path'],
                                      options.score_id)
        if tweet_contents is None:
            logger.warning('No score data found.')
            sys.exit(1)

        if (options.dry_run):
            print(tweet_contents)
        else:
            tweet(config['TwitterOAuth'], tweet_contents)
    except Exception:
        from traceback import format_exc
        logger.critical(format_exc())
