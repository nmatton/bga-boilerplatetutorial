#!/bin/bash
SRC=~/bga/bga-boilerplatetutorial/ # with trailing slash
NAME=boilerplatetutorial

# Sass
sass "$NAME.scss" "$NAME.css"

# Copy
rsync $SRC/$NAME.css ~/bga/studio/$NAME/
rsync $SRC/$NAME.css.map ~/bga/studio/$NAME/
