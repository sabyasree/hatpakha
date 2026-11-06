<?php

// Bengali strings missing from Filament's own `bn` pack (desk is Bengali-only, D36).

return array (
  'management_schema' => 
  array (
    'actions' => 
    array (
      'label' => 'অথেন্টিকেটর অ্যাপ',
      'below_content' => 'লগইনের সময় যাচাইয়ের জন্য একটি নিরাপদ অ্যাপ থেকে সাময়িক কোড নিন।',
      'messages' => 
      array (
        'enabled' => 'চালু',
        'disabled' => 'বন্ধ',
      ),
    ),
  ),
  'login_form' => 
  array (
    'label' => 'অথেন্টিকেটর অ্যাপের কোড ব্যবহার করুন',
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
      ),
    ),
    'recovery_code' => 
    array (
      'label' => 'অথবা একটি রিকভারি কোড দিন',
      'validation_attribute' => 'রিকভারি কোড',
      'messages' => 
      array (
        'invalid' => 'রিকভারি কোডটি ঠিক নয়।',
      ),
    ),
  ),
);
