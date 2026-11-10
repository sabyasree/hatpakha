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
        'messages' => 
        array (
          'invalid' => 'কোডটি ঠিক নয়।',
          'rate_limited' => 'অনেকবার চেষ্টা হয়েছে। একটু পরে আবার চেষ্টা করুন।',
        ),
      ),
    ),
  ),
  'show_new_recovery_codes' => 
  array (
    'modal' => 
    array (
      'heading' => 'নতুন রিকভারি কোড',
      'description' => 'এই রিকভারি কোডগুলো নিরাপদ জায়গায় রেখে দিন। এগুলো একবারই দেখানো হবে; অথেন্টিকেটর অ্যাপ হারালে এগুলো লাগবে:',
      'actions' => 
      array (
        'submit' => 
        array (
          'label' => 'বন্ধ করুন',
        ),
      ),
    ),
  ),
);
