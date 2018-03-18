#!/bin/bash

list="index web_update jlicense link download lists"

mkdir -p ../web
cp -r image *.txt hengband.css ../web

for v in $list; do
	echo ${v}
	asciidoctor --no-header-footer --out-file=tmp.txt \
		--backend=html5 -a linkcss -a stylesheet=hengband.css \
		${v}.adoc 
	cat template.html | sed '/<!--main contents-->/r tmp.txt' > ../web/${v}.html
done

rm tmp.txt

