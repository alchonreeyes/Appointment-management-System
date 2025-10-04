; This is a conceptual example for x86-64 Linux (NASM syntax).
; It is not a complete, runnable program.
; It requires linking with the MySQL client library (e.g., gcc -o connect main.o -lmysqlclient)

section .data
    host        db "localhost", 0
    user        db "root", 0
    passwd      db "", 0
    dbname      db "my_database", 0
    
    success_msg db "Connected successfully", 10, 0
    fail_msg    db "Connection failed", 10, 0
    init_fail_msg db "mysql_init failed", 10, 0

section .text
    global main
    
    ; External C functions from libmysqlclient and libc
    extern mysql_init
    extern mysql_real_connect
    extern mysql_close
    extern printf
    extern exit

main:
    ; Stack alignment for C calls
    push    rbp
    mov     rbp, rsp
    sub     rsp, 8 

    ; MYSQL *mysql = mysql_init(NULL);
    mov     rdi, 0          ; Argument is NULL
    call    mysql_init
    
    test    rax, rax        ; Check if mysql_init returned NULL
    jz      .init_failed
    
    mov     r12, rax        ; Save the MYSQL* handle in r12

    ; mysql_real_connect(mysql, host, user, passwd, db, port, unix_socket, client_flag)
    ; Arguments are passed in registers: RDI, RSI, RDX, RCX, R8, R9, then stack
    mov     rdi, r12        ; 1st arg: MYSQL* handle
    lea     rsi, [rel host] ; 2nd arg: host
    lea     rdx, [rel user] ; 3rd arg: user