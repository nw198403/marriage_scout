<?php
// index.php：トップページ

require_once __DIR__ . '/includes/layout.php';

render_header('トップ', 'public', 'home');
?>
<section class="hero" style="--hero-img:url('<?= h(url('assets/img/hero.jpg')) ?>');" aria-label="手をつないで並んで歩く二人の後ろ姿">
  <div class="hero-inner">
    <div class="hero-text">
      <h1 class="hero-title">結婚相談所・カウンセラーは、<br>自分で選ぶ時代へ</h1>
      <p class="hero-sub">婚活は、カウンセラー選びで決まる・あなたに合う人から、スカウトが届く</p>
      <p class="hero-lead">届いたスカウトを比べて、<br>これからの婚活に並んで走ってくれる人を選べます</p>
      <div class="hero-actions">
        <a class="btn" href="<?= h(url('register.php?role=member')) ?>">会員登録（無料）</a>
        <a class="btn btn-secondary" href="<?= h(url('register.php?role=counselor')) ?>">カウンセラーとして登録</a>
      </div>
      <p class="hero-login"><a href="<?= h(url('login.php')) ?>">ログインはこちら</a></p>
    </div>
  </div>
</section>

<section class="about" id="about">
  <div class="about-inner">
    <p class="section-label">ABOUT US</p>
    <h2 class="about-title">婚活の第一歩・伴走者選びを、<br>納得のいくものに。</h2>
    <div class="about-body">
      <p>結婚には興味がある。 でも、出会いの作り方や、婚活の進め方がわからない。<br class="pc-only">ー そんな悩みを持つ人は、たくさんいます。</p>
      <p>その解決策として、効率よく婚活を進められる<mark>結婚相談所</mark>が注目を集めています。<br class="pc-only">ただ、相談所は数が多く、料金は高いため、慎重に検討した結果、<br class="pc-only"><mark>最初の一歩を踏み出せない人も少なくありません。</mark></p>
      <p>そして、相談所選びと同じくらい大切なのが、<br class="pc-only"><mark>婚活に伴走してくれるカウンセラー選び</mark>です。<br class="pc-only">同じ相談所でも、担当するカウンセラーによって、婚活の進み方は大きく変わります。</p>
      <p>この婚活スカウトは、<br class="pc-only"><mark>あなたの婚活に本気で向き合ってくれるカウンセラーと出会うための場所</mark>です。<br class="pc-only">得意分野や実績、星評価をもとに、じっくり比べて、納得して選んでください。</p>
    </div>
  </div>
</section>

<section class="how" id="how">
  <p class="section-label">HOW IT WORKS</p>
  <h3 class="how-heading">自分に合うカウンセラーを、<mark>自分で選ぶ</mark>。</h3>
  <p class="how-lead">プロフィールを登録すれば、<br>あなたのサポートを希望するカウンセラーからスカウトが届きます。</p>
  <div class="steps">
    <div class="step">
      <div class="step-head"><span class="step-no">1</span><strong>プロフィール登録</strong></div>
      <span class="muted">氏名・連絡先は非公開。<br>1分で完了</span>
    </div>
    <div class="step-arrow" aria-hidden="true">→</div>
    <div class="step">
      <div class="step-head"><span class="step-no">2</span><strong>スカウトを比較</strong></div>
      <span class="muted">届いたスカウトから、<br>気になる人を選ぶ</span>
    </div>
    <div class="step-arrow" aria-hidden="true">→</div>
    <div class="step">
      <div class="step-head"><span class="step-no">3</span><strong>面談して決定</strong></div>
      <span class="muted">話してみて納得したら、<br>依頼を確定</span>
    </div>
  </div>
  <div class="how-cta">
    <p>まずはプロフィールを登録してみましょう。</p>
    <a class="btn" href="<?= h(url('register.php?role=member')) ?>">登録（無料）</a>
  </div>
</section>
<script>
(function(){function f(){var h=document.querySelector('.site-header');if(h)document.documentElement.style.setProperty('--hdr',h.offsetHeight+'px');}
f();addEventListener('resize',f);addEventListener('load',f);})();
</script>
<?php render_footer(); ?>
