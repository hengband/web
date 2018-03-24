#!/bin/bash

list="index web_update jlicense link download lists history score"

mkdir -p ../web
cp -r image *.txt hengband.css ../web

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

rm tmp.txt

