#!/bin/bash

grep -nRi --color=always "$*" *.php src tests/*.php
