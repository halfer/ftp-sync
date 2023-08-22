#!/bin/sh

docker run \
  -v `pwd`:/project -it \
  php:7.3-cli-alpine \
  sh
