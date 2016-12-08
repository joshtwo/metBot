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
    $NEWARGS && php run.php "${NEWARGS[@]}" || php run.php "${@:1}"
  else
    echo "Bye!"
    exit
  fi
}

echo "metBot Beta 7"
php run.php "${@:1}"
while :
do
  if [ -f ./core/status/restart.bot ]
  then
    # see if there are new args in the restart
    if [ -s ./core/status/restart.bot ]
    then
      echo Using new arguments `cat ./core/status/restart.bot`
      eval 'NEWARGS=('`cat ./core/status/restart.bot`')'
      php run.php "${NEWARGS[@]}"
    else
      echo Restart file empty, using old arguments...
      $NEWARGS && php run.php "${$NEWARGS[@]}" || php run.php "${@:1}"
    fi
  else
    if [ -f ./core/status/close.bot ]
    then
      echo "Bye!"
      exit
    else # in case PHP crashes, we need to fix the STDIN handle
      php -r 'stream_set_blocking(STDIN, true);'
      ask_restart
    fi
  fi
done
