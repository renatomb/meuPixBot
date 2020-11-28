<?php

function valida_uuid($uuid_text) {
	return preg_match("/^[0-9a-fA-F]{32}$/",$uuid_text);
}

function valida_telefone($fone){
   return preg_match("^\(?\d{2}\)?[\s-]?[\s9]\d{4}-?\d{4}$",$fone);
}

function valida_cnpj($cnpj) {
    if (!preg_match("/^[0-9]{14}$/",$cnpj) || $cnpj=='00000000000000' || $cnpj=='11111111111111' || $cnpj=='22222222222222' || $cnpj=='33333333333333' || $cnpj=='44444444444444' || $cnpj=='55555555555555' || $cnpj=='66666666666666' || $cnpj=='77777777777777' || $cnpj=='88888888888888' || $cnpj=='99999999999999') { return false; }
    else {
       $soma1 = ($cnpj[0] * 5) +
       ($cnpj[1] * 4) +
       ($cnpj[2] * 3) +
       ($cnpj[3] * 2) +
       ($cnpj[4] * 9) +
       ($cnpj[5] * 8) +
       ($cnpj[6] * 7) +
       ($cnpj[7] * 6) +
       ($cnpj[8] * 5) +
       ($cnpj[9] * 4) +
       ($cnpj[10] * 3) +
       ($cnpj[11] * 2);
       $resto = $soma1 % 11;
       $digito1 = $resto < 2 ? 0 : 11 - $resto;
       $soma2 = ($cnpj[0] * 6) +
       ($cnpj[1] * 5) +
       ($cnpj[2] * 4) +
       ($cnpj[3] * 3) +
       ($cnpj[4] * 2) +
       ($cnpj[5] * 9) +
       ($cnpj[6] * 8) +
       ($cnpj[7] * 7) +
       ($cnpj[8] * 6) +
       ($cnpj[9] * 5) +
       ($cnpj[10] * 4) +
       ($cnpj[11] * 3) +
       ($cnpj[12] * 2);
       $resto = $soma2 % 11;
       $digito2 = $resto < 2 ? 0 : 11 - $resto;
       return (($cnpj[12] == $digito1) && ($cnpj[13] == $digito2));
    }
}

function valida_cpf($cpf) {	// Verifiva se o número digitado contém todos os digitos
    if (!preg_match("/^[0-9]{11}$/",$cpf) || $cpf == '00000000000' || $cpf == '11111111111' || $cpf == '22222222222' || $cpf == '33333333333' || $cpf == '44444444444' || $cpf == '55555555555' || $cpf == '66666666666' || $cpf == '77777777777' || $cpf == '88888888888' || $cpf == '99999999999') { return false; }
    else {
        for ($t = 9; $t < 11; $t++) {
            for ($d = 0, $c = 0; $c < $t; $c++) {
                $d += $cpf{$c} * (($t + 1) - $c);
            }
            $d = ((10 * $d) % 11) % 10;
            if ($cpf{$c} != $d) {
                return false;
            }
        }
        return true;
    }
}

?>