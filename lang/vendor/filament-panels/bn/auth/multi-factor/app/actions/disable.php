<?php

// Bengali strings missing from Filament's own `bn` pack (desk is Bengali-only, D36).

return array (
  'modal' => 
  array (
    'form' => 
    array (
      'code' => 
      array (
        'label' => 'অথেন্টিকেটর অ্যাপের ৬ অঙ্কের কোড দিন',
        'validation_attribute' => 'কোড',
        'actions' => 
        array (
          'use_recovery_code' => 
          array (
            'label' => 'বদলে রিকভারি কোড ব্যবহার করুন',
          ),
        ),
        'messages' => 
        array (
          'invalid' => 'কোডটি ঠিক নয়।',
          'rate_limited' => 'অনেকবার চেষ্টা হয়েছে। একটু পরে আবার চেষ্টা করুন।',
        ),
      ),
      'recovery_code' => 
      array (
        'label' => 'অথবা একটি রিকভারি কোড দিন',
        'validation_attribute' => 'রিকভারি কোড',
        'messages' => 
        array (
          'invalid' => 'রিকভারি কোডটি ঠিক নয়।',
          'rate_limited' => 'অনেকবার চেষ্টা হয়েছে। একটু পরে আবার চেষ্টা করুন।',
        ),
      ),
    ),
  ),
);
