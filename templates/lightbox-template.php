<?php
/**
 * Template para el modal de Banorte
 */
?>
<div id="paymentFrameWrapper" style="display: none;">
    <div class="backdrop"></div>
    <div class="dialog">
        <header>
            <div class="title">Procesando pago Banorte…</div>
            <button class="close" type="button">&times;</button>
        </header>
        <iframe id="paymentFrame" name="paymentFrame" allow="payment *; fullscreen"></iframe>
    </div>
</div>

<style>
#paymentFrameWrapper {
    position: fixed;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    z-index: 9999;
    display: none;
}

#paymentFrameWrapper .backdrop {
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    background: rgba(0,0,0,0.5);
}

#paymentFrameWrapper .dialog {
    position: absolute;
    top: 5%;
    left: 10%;
    right: 10%;
    bottom: 5%;
    background: white;
    border-radius: 10px;
    display: flex;
    flex-direction: column;
    overflow: hidden;
}

#paymentFrameWrapper header {
    display: flex;
    justify-content: space-between;
    align-items: center;
    padding: 10px 15px;
    border-bottom: 1px solid #eee;
    background: #fafafa;
}

#paymentFrameWrapper .close {
    background: none;
    border: none;
    font-size: 24px;
    cursor: pointer;
}

#paymentFrame {
    width: 100%;
    height: 100%;
    border: none;
}
</style>