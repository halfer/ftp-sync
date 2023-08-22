#!/bin/sh

docker run \
  -v `pwd`:/project -it \
  php:8.2-cli-alpine \
  sh
