@echo off
echo metBot Beta 7
if not exist core\bat\phpdir.bat goto getpath
if exist core\bat\phpdir.bat call core\bat\phpdir.bat
goto php

:getpath
set /p path=Enter the full path of php.exe^>
echo set path=%path% > core\bat\phpdir.bat
goto php

:php
%path% run.php %0 %1 %2 %3 %4 %5 %6 %7 %8 %9
goto phpend

:phpend
if exist core\status\restart.bot goto php
if exist core\status\close.bot   goto quit
echo metBot has stopped.
goto askrestart

:quit
echo metBot has stopped.

:askrestart
set /p reply="Retry? (y/n): "
if %reply%=="y" goto php
if %reply%=="n" goto quit
echo Unknown option ^"%reply%^"
goto askrestart
