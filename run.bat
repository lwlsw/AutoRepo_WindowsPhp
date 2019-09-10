@echo off
ren bin\7z\7z.exe.bak 7z.exe
ren bin\ar\ar.exe.bak ar.exe
ren bin\php\php.exe.bak php.exe

bin\php\php handler.php debs

ren bin\7z\7z.exe 7z.exe.bak
ren bin\ar\ar.exe ar.exe.bak
ren bin\php\php.exe php.exe.bak

echo 清理残留的垃圾文件
del /q tmp\*

pause
exit