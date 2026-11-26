<?php
$policyNames=['return-refund'=>'return-refund','terms-conditions'=>'terms-conditions','faqs'=>'faqs','privacy-policy'=>'privacy-policy'];
$policy=json_decode(file_get_contents(__DIR__.'/../assets/content/'.$policyNames[$page].'.json'),true,512,JSON_THROW_ON_ERROR);
?>
<section class="customer-policy <?= $page==='faqs'?'customer-faq':'' ?>" aria-labelledby="customer-policy-title">
    <h1 id="customer-policy-title"><?= e($policy['title']) ?></h1>
    <div class="customer-policy-content">
        <?php foreach($policy['intro'] as $paragraph): ?><p class="customer-policy-intro"><?= e($paragraph) ?></p><?php endforeach; ?>
        <?php foreach($policy['sections'] as $index=>$section): ?>
        <section class="customer-policy-section"><h2><?= $index+1 ?>. <?= e($section['title']) ?></h2>
            <?php if($page==='faqs'): ?>
                <?php foreach($section['questions'] as $question): ?><details class="customer-faq-item"><summary><?= e($question['question']) ?></summary><div><?php foreach($question['paragraphs'] as $paragraph): ?><p><?= e($paragraph) ?></p><?php endforeach; ?></div></details><?php endforeach; ?>
            <?php else: ?>
                <?php foreach($section['paragraphs'] ?? [] as $paragraphIndex=>$paragraph): ?><p><?= e($paragraph) ?></p><?php if($paragraphIndex===0 && !empty($section['items'])): ?><ul><?php foreach($section['items'] as $item): ?><li><?= e($item) ?></li><?php endforeach; ?></ul><?php endif; ?><?php endforeach; ?>
                <?php if(!empty($section['contact'])): ?><p><strong>Email:</strong> <a href="mailto:hello@aromehouse.test">hello@aromehouse.test</a><br><strong>WhatsApp:</strong> <a href="https://wa.me/6281234567890" target="_blank" rel="noopener">+62 812-3456-7890</a></p><a href="?page=contact" class="customer-policy-contact">Contact Customer Support</a><?php endif; ?>
            <?php endif; ?>
        </section>
        <?php endforeach; ?>
        <?php if($page==='faqs'): ?><p>See our <a href="?page=shipping-policy">Shipping Policy</a>, <a href="?page=return-refund">Return & Refund Policy</a>, and <a href="?page=contact">Customer Support</a> for further information.</p><?php endif; ?>
    </div>
</section>
