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

history_list="history1.0.8"

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

