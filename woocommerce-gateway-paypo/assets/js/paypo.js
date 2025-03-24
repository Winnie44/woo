const productTypeInput = document.getElementById('woocommerce_paypo_product_type');
const productTypeInputValue = productTypeInput.value;
const installmentCountInput = document.getElementById('woocommerce_paypo_installment_count');

function setInstallmentCount() {

    if ( productTypeInput.value == 'CORE' ) {
        installmentCountInput.value = 4;
        installmentCountInput.disabled = true;
    } else {
        installmentCountInput.disabled = false;
        if ( installmentCountInput.value == '' || installmentCountInput.value == null || productTypeInputValue == 'CORE' ) {
            installmentCountInput.value = 1;
        }
    }
}

window.addEventListener('DOMContentLoaded', setInstallmentCount );
productTypeInput.addEventListener( 'click', setInstallmentCount );