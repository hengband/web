#!/bin/bash

mkdir -p ../web ../web/history
cp -r image *.txt hengband.css ../web

list="index web_update jlicense link download lists history"

for v in $list; do
	echo ${v}
	asciidoctor --no-header-footer --out-file=tmp.txt \
		--backend=html5 -a linkcss -a stylesheet=hengband.css \
		${v}.adoc 
	cat template.html | sed '/<!--main contents-->/r tmp.txt' \
	 | sed '/<!--head-->/r head.html' \
	 | sed '/<!--header-->/r header.html' \
	 | sed '/<!--footer-->/r footer.html' \
	 > ../web/${v}.html
done

score_list="score"

for v in $score_list; do
	echo ${v}
	asciidoctor --no-header-footer --out-file=tmp.txt \
		--backend=html5 -a linkcss -a stylesheet=hengband.css \
		${v}.adoc
	cat scorelinks.html >> tmp.txt
	cat template.html | sed '/<!--main contents-->/r tmp.txt' \
	 | sed '/<!--head-->/r head_score.html' \
	 | sed '/<!--header-->/r header.html' \
	 | sed '/<!--footer-->/r footer.html' \
	 > ../web/${v}.html
done

history_list="history0.0.12 history0.1.0 history0.1.0post history0.1.1 history0.1.2 history0.1.3 history0.2.0 history0.2.1 history0.2.2 history0.2.3 history0.2.3pre history0.2.4 history0.2.5 history0.3.0 history0.3.1 history0.3.2 history0.3.3 history0.3.4 history0.3.5 history0.3.6 history0.4.0 history0.4.2 history0.4.5 history0.4.6 history0.4.7 history0.4.8 history0.4.10 history1.0.0 history1.0.1 history1.0.3 history1.0.4 history1.0.5 history1.0.6 history1.0.7 history1.0.8 history1.0.9 history1.0.10 history1.0.11 history1.1.0c1 history1.1.0 history1.2.0and1.3.0 history1.2.1 history1.2.2and1.3.1 history1.4.0and1.5.0 history1.4.1and1.5.1 history1.4.2and1.5.2 history1.4.3and1.5.3 history1.4.4and1.5.4 history1.4.5and1.6.0 history1.4.6and1.6.1"

for v in $history_list; do
	echo ${v}
	asciidoctor --no-header-footer --out-file=tmp.txt \
		--backend=html5 -a linkcss -a stylesheet=../hengband.css \
		history/${v}.adoc 
	cat template.html | sed '/<!--main contents-->/r tmp.txt' \
	 | sed '/<!--head-->/r head.html' \
	 | sed '/<!--header-->/r header.html' \
	 | sed '/<!--footer-->/r footer.html' \
	 > ../web/history/${v}.html
done

rm tmp.txt

