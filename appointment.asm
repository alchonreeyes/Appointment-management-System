; ============================================================================
; APPOINTMENT MANAGEMENT SYSTEM - DATABASE CONNECTION MODULE
; ============================================================================
; 
; PURPOSE:
;   Establishes a connection to a MySQL database for managing appointments.
;   Uses MySQL C library functions to initialize and connect to the database.
;
; DEPENDENCIES:
;   - MySQL C libraries (mysql_init, mysql_real_connect, mysql_close)
;   - Standard C library (printf, exit)
;
; DATABASE CONFIGURATION:
;   - Host:     localhost
;   - User:     root
;   - Password: (empty/blank)
;   - Database: appointmentdb
;
; MAIN PROCEDURE:
;   1. Initializes MySQL connection handler via mysql_init()
;   2. Validates successful initialization (checks if rax != NULL)
;   3. Stores connection handler in r12 register for later use
;   4. Prepares to call mysql_real_connect() with database credentials
;
; REGISTERS USED:
;   - rdi: Function argument (mysql handler pointer)
;   - rsi: Host address pointer
;   - rdx: User credentials pointer
;   - r12: Stores the MySQL connection handler
;   - rbp: Stack frame pointer
;   - rsp: Stack pointer
;
; ERROR HANDLING:
;   - Jumps to .init_failed label if mysql_init returns NULL (rax == 0)
;   - Connection failure messages defined in data section
;
; ============================================================================
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
