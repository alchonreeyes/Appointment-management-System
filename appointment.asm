section .data
    host        db "localhost", 0
    user        db "root", 0
    passwd      db "", 0
    dbname      db "appointmentdb", 0
    
    success_msg db "Connected successfully", 10, 0
    fail_msg    db "Connection failed", 10, 0
    init_fail_msg db "mysql_init failed", 10, 0

section .text
    global main
    
    extern mysql_init
    extern mysql_real_connect
    extern mysql_close
    extern printf
    extern exit

main:
    push    rbp
    mov     rbp, rsp
    sub     rsp, 8 
    
    mov     rdi, 0
    call    mysql_init
    
    test    rax, rax
    jz      .init_failed
    
    mov     r12, rax

    mov     rdi, r12
    lea     rsi, [rel host]
    lea     rdx, [rel user]
