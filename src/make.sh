#!/bin/sh
asciidoctor --no-header-footer  --out-file=tmp.txt --backend=html5 -a linkcss -a stylesheet=hengband.css web_update.adoc 

cat template.html | sed '/<!--main contents-->/r tmp.txt' > web_update.html
