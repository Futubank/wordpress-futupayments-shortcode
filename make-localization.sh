#!/bin/bash
set -e
NAME=futupayments
VERSION=1.1
echo $PATH
pushd futupayments-shortcode/languages
cp -f ${NAME}-ru_RU.po $NAME.po
xgettext \
    --from-code="utf-8" \
    --join-existing \
    --default-domain=${NAME} \
    --language=PHP \
    --keyword=__ \
    --keyword=_e \
    --sort-by-file \
    --package-name=$NAME \
    --package-version=$VERSION \
    ../*.php \
    ../templates/*.php
mv -f $NAME.po ${NAME}-ru_RU.po
msgfmt ${NAME}-ru_RU.po --output-file=${NAME}-ru_RU.mo
popd
echo "done"
