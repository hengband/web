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


def create_tweet(score_data):
    summary = (u"【新着スコア】{personality_name}{name} Score:{score} "
               u"{race_name} {class_name}{realms_name} {death_reason} {depth}階"
               ).format(**score_data)

    dump_url = ("https://hengband.osdn.jp/score/show_dump.php?score_id={}"
                ).format(score_data['score_id'])
    screen_url = ("https://hengband.osdn.jp/score/show_screen.php?score_id={}"
                  ).format(score_data['score_id'])

    tweet = (u"{summary}\n\n"
             u"ダンプ: {dump_url}\n"
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
        score_data = get_score_data(config['ScoreDB']['path'],
                                    options.score_id)
        if score_data is None:
            logger.warning('No score data found.')
            sys.exit(1)

        tweet_contents = create_tweet(score_data)
        if (options.dry_run):
            print(tweet_contents)
        else:
            tweet(config['TwitterOAuth'], tweet_contents)
    except Exception:
        from traceback import format_exc
        logger.critical(format_exc())
