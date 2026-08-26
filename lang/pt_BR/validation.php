<?php

return [

    'after'    => 'O campo :attribute deve ser uma data posterior a :date.',
    'after_or_equal' => 'O campo :attribute deve ser uma data igual ou posterior a :date.',
    'before'   => 'O campo :attribute deve ser uma data anterior a :date.',
    'date'     => 'O campo :attribute não é uma data válida.',
    'required' => 'O campo :attribute é obrigatório.',
    'email' => 'O campo :attribute deve ser um e-mail válido.',
    'min' => [
        'string' => 'O campo :attribute deve ter no mínimo :min caracteres.',
        'array' => 'O campo :attribute deve ter no mínimo :min itens.',
    ],
    'max' => [
        'string' => 'O campo :attribute não pode ter mais de :max caracteres.',
        'array' => 'O campo :attribute não pode ter mais de :max itens.',
    ],
    'confirmed' => 'A confirmação do campo :attribute não confere.',
    'in' => 'O :attribute selecionado é inválido.',
    'unique' => 'O :attribute já está em uso.',

    'attributes' => [
        'name' => 'nome',
        'email' => 'e-mail',
        'password' => 'senha',
        'password_confirmation' => 'confirmação da senha',
        'current_password' => 'senha atual',
        'cpf' => 'CPF',
        'phone' => 'telefone',
        'product_id' => 'produto',
        'payment_method' => 'forma de pagamento',
        'coupon_code' => 'código do cupom',
        'payment_token' => 'token do cartão',
    ],

];
