#!/bin/sh

ask_restart()
{
  echo -n "metBot has stopped. Retry? (y/n): "
  read REPLY || exit
  while [[ $REPLY != "y" && $REPLY != "n" ]]
  do
    echo -n "Unknown option \"$REPLY\". Retry? (y/n): "
    read REPLY
  done
  if [ $REPLY == "y" ]
  then
    php run.php "${@:1}"
  else
    echo "Bye!"
    exit
  fi
}

echo "metBot Beta 7"
while :
do
  php run.php "${@:1}"
  if [ -f ./core/status/restart.bot ]
  then
    php run.php "${@:1}"
  else
    if [ -f ./core/status/close.bot ]
    then
      echo "Bye!"
      exit
    else
      ask_restart
    fi
  fi
done
