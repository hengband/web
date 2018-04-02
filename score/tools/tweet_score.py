#!/usr/bin/python
# -*- coding: utf-8

'''スコアをツイートする

OSDNのサーバでrequests_oauthlibを使う方法のメモ

1. 環境変数PYTHONUSERBASEでインストール先をWebコンテンツ上の任意のディレクトリに指定し、
   pipに--userオプションをつけてインストールを実行
   以下は /home/groups/h/he/hengband/htdocs/score/local 以下にインストールする例

   `$ PYTHONUSERBASE=/home/groups/h/he/hengband/htdocs/score/local
      pip install --user requests_oauthlib`

2. パスは通っているはずなのにシステムにインストールされているrequestsとurllib3が何故か読み込みに失敗するので、
   上でインストールしたディレクトリにコピーする

   `$ cp -a /usr/lib/python2.7/dist-packages/requests
      /usr/lib/python2.7/dist-packages/urllib3
      /home/groups/h/he/hengband/htdocs/score/local/lib/python2.7/site-packages`

3. sys.path.appendで上でインストールしたディレクトリにパスを通してからrequests_oauthlibをimportする

   `import sys`
   `sys.path.append('/home/groups/h/he/hengband/htdocs/score/local/lib/python2.7/site-packages')`
   `import requests_oauthlib`
'''

import sys
import datetime
import sqlite3
import gzip
import re
import config


def get_score_data(score_id):
    '''DBからスコアデータを取得する。

    Args:
        score_id: 取得するスコアのスコアID。
            Noneの場合最新のスコアを取得する。

    Returns:
        取得したスコアのデータを格納した辞書。
        指定のスコアIDに該当するスコアが見つからない場合None。
    '''
    if score_id is None:
        cond = 'ORDER BY score_id DESC LIMIT 1'
    else:
        cond = 'WHERE score_id = :score_id'

    with sqlite3.connect(config.config['ScoreDB']['path']) as con:
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


def get_daily_score_stats(year, month, day):
    '''DBから指定した日付のスコア統計データを得る

    Args:
        year: 指定する年。
        month: 指定する月。
        day: 指定する日。

    Returns:
        取得したスコア統計データを格納した辞書。
        'total_count': 総スコア件数, 'winner_count': 勝利スコア件数
    '''
    with sqlite3.connect(config.config['ScoreDB']['path']) as con:
        con.row_factory = sqlite3.Row
        sql = '''
SELECT
  count(*) AS total_count,
  count(winner = 1 OR NULL) AS winner_count
FROM
  scores
WHERE
  date >= date('{target_date}') AND date < date('{target_date}', '+1 day')
'''.format(target_date=datetime.date(year, month, day).isoformat())
        c = con.execute(sql, {})
        score = c.fetchall()

    return score[0]


def get_death_reason_detail(score_id):
    '''ダンプファイル内から詳細な死因を取得する。

    Args:
        score_id: ダンプファイルのスコアID。

    Returns:
        詳細な死因を表す文字列。
        ダンプファイルが無い、もしくは詳細な死因が見つからなかった場合None。
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


def create_tweet(score_id):
    '''ツイートするメッセージを生成する。

    Args:
        score_id: 指定するスコアID。Noneの場合最新のスコアを取得する。

    Returns:
        生成したツイートメッセージ文字列。
        なんらかの理由により生成できなかった場合None。
    '''
    score_data = get_score_data(score_id)
    if score_data is None:
        return None

    death_reason_detail = get_death_reason_detail(score_data['score_id'])
    if death_reason_detail is None:
        death_reason_detail = (u"{0} {1}階"
                               .format(score_data['death_reason'],
                                       score_data['depth']))

    summary = (u"【新着スコア】{personality_name}{name} Score:{score}\n"
               u"{race_name} {class_name}{realms_name}\n"
               u"{death_reason_detail}"
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


def create_daily_stats_tweet(year, month, day):
    '''デイリースコア統計データのツイートを生成する

    Args:
        year: 指定する年。
        month: 指定する月。
        day: 指定する日。

    Returns:
        生成したツイートメッセージ文字列。
        なんらかの理由により生成できなかった場合None。
    '''
    daily_stats = get_daily_score_stats(year, month, day)

    tweet = (u"{year}年{month}月{day}日のスコア\n"
             u"全 {total_count} 件, 勝利 {winner_count} 件\n"
             u"#hengband"
             ).format(year=year, month=month, day=day,
                      **daily_stats)

    return tweet


def tweet(tweet_contents):
    '''ツイートする。

    Args:
        oauth: requests_oauthlib.OAuth1Sessionの引数に渡すOAuth認証パラメータ。
        tweet_contents: ツイートする内容を表す文字列。
    '''
    from requests_oauthlib import OAuth1Session
    from requests.adapters import HTTPAdapter
    from requests import codes
    from logging import getLogger
    logger = getLogger(__name__)

    twitter = OAuth1Session(**config.config['TwitterOAuth'])

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
    '''コマンドライン引数をパースする。

    Returns:
        パースした結果を表す辞書。OptionParser.parse_args()のドキュメント参照。
    '''
    from optparse import OptionParser
    parser = OptionParser()
    parser.add_option(
        "-s", "--score-id",
        type="int", dest="score_id",
        help="Tweet score with specified id.\n"
             "When this option and -d are not set, latest score is specified.")
    parser.add_option(
        "-d", "--daily-stats",
        type="string", dest="stats_date",
        help="Tweet statistics of the score of the specified day.")
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
    '''ロガーをセットアップする。

    Args:
        log_file: ロガーの出力先ファイル名。
            Noneの場合、ファイルには出力せず標準エラー出力のみに出力する。
    '''
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
        config.parse(options.config_file)
        if 'Python' in config.config:
            sys.path.append(config.config['Python']['local_lib_path'])

        if options.stats_date:
            target_datetime = datetime.datetime.strptime(
                options.stats_date, "%Y-%m-%d")
            tweet_contents = create_daily_stats_tweet(
                target_datetime.year,
                target_datetime.month,
                target_datetime.day
            )
        else:
            tweet_contents = create_tweet(options.score_id)

        if tweet_contents is None:
            logger.warning('No score data found.')
            sys.exit(1)

        if (options.dry_run):
            print(tweet_contents.encode("UTF-8"))
        else:
            tweet(tweet_contents)
    except Exception:
        from traceback import format_exc
        logger.critical(format_exc())
