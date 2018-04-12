#!/usr/bin/python
# -*- coding: utf-8

'''ツイッタークラス

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


class Twitter:
    def __init__(self,
                 consumer_key, consumer_secret,
                 access_token, access_token_secret,
                 logger=None):
        self.__oauth = {
            'client_key': consumer_key,
            'client_secret': consumer_secret,
            'resource_owner_key': access_token,
            'resource_owner_secret': access_token_secret,
        }

        from logging import getLogger
        self.__logger = logger if logger else getLogger(__name__)

    def post_tweet(self, tweet_contents):
        '''ツイートを投稿する。

        Args:
            tweet_contents: ツイートする内容を表す文字列。または文字列の配列。
                文字列の配列の場合、それぞれの内容を一連のスレッドとして投稿する。
        '''
        from requests_oauthlib import OAuth1Session
        from requests.adapters import HTTPAdapter
        from requests import codes

        if isinstance(tweet_contents, basestring):
            tweet_contents = [tweet_contents]

        twitter = OAuth1Session(**self.__oauth)
        twitter.mount("https://", HTTPAdapter(max_retries=5))

        self.__logger.info("Posting to Twitter...")
        in_reply_to_status_id = None
        screen_name = None
        for tw in tweet_contents:
            if in_reply_to_status_id:
                params = {"status":
                          u"@{screen_name} {tw}"
                          .format(screen_name=screen_name, tw=tw),
                          "in_reply_to_status_id": in_reply_to_status_id}
            else:
                params = {"status": tw}

            self.__logger.info(u"Tweet contents:\n{}".format(tw))
            res = twitter.post(
                "https://api.twitter.com/1.1/statuses/update.json",
                params=params)

            if res.status_code == codes['ok']:
                self.__logger.info("Success.")
                res_json = res.json()
                in_reply_to_status_id = res_json["id"]
                screen_name = res_json["user"]["screen_name"]
            else:
                self.__logger.warning(
                    "Failed to post: {code}, {json}"
                    .format(code=res.status_code, json=res.json()))


def main():
    import sys
    import config

    if len(sys.argv) < 3:
        return

    config.parse(sys.argv[1])

    from logging import getLogger, StreamHandler, INFO
    logger = getLogger(__name__)
    logger.setLevel(INFO)
    sh = StreamHandler()
    logger.addHandler(sh)

    twitter = Twitter(logger=logger, **config.config['TwitterOAuth'])
    tweet_contents = [i.decode('UTF-8') for i in sys.argv[2:]]
    twitter.post_tweet(tweet_contents)


if __name__ == '__main__':
    main()
