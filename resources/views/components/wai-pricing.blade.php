<section class="wai-pricing-section" id="paketler">

    <div class="container">

        <div class="wai-pricing-intro reveal">

            <div class="section-tag">
                Paketler
            </div>

            <h2 class="section-title">
                İşletmenize uygun
                <span>WAI paketini seçin.</span>
            </h2>

            <p class="section-copy">
                Önce ücretsiz deneyin. İhtiyacınıza uygun pakete dilediğiniz zaman geçin.
            </p>

        </div>

        <div class="wai-pricing-grid reveal">

            {{-- START --}}
            <article class="wai-price-card">

                <div class="wai-price-plan">START</div>

                <p class="wai-price-audience">
                    WAI ile satış ve müşteri iletişimine başlamak isteyen işletmeler için.
                </p>

                <div class="wai-price">
                    <span>₺</span>
                    <strong>2.990</strong>
                    <small>/ ay</small>
                </div>

                <div class="wai-price-limit">
                    <b>1.500</b> AI cevabı / ay
                </div>

                <div class="wai-price-divider"></div>

                <ul class="wai-price-features">
                    <li><i>✓</i><span>1 WhatsApp numarası</span></li>
                    <li><i>✓</i><span>7/24 yapay zekâ cevaplama</span></li>
                    <li><i>✓</i><span>Firma bilgilerini öğrenme</span></li>
                    <li><i>✓</i><span>Ürün / hizmet yönetimi</span></li>
                    <li><i>✓</i><span>WhatsApp Gelen Kutusu</span></li>
                    <li><i>✓</i><span>İnsan devralma</span></li>
                </ul>

                <a href="/admin/register" class="wai-price-button secondary">
                    <span>Ücretsiz Başla</span>
                    <b>→</b>
                </a>

            </article>

            {{-- PRO --}}
            <article class="wai-price-card featured">

                <div class="wai-popular">
                    EN ÇOK TERCİH EDİLEN
                </div>

                <div class="wai-price-plan">PRO</div>

                <p class="wai-price-audience">
                    Satış sürecini, CRM'i ve müşteri takibini WAI'ye taşımak isteyen işletmeler için.
                </p>

                <div class="wai-price">
                    <span>₺</span>
                    <strong>5.990</strong>
                    <small>/ ay</small>
                </div>

                <div class="wai-price-limit">
                    <b>5.000</b> AI cevabı / ay
                </div>

                <div class="wai-price-divider"></div>

                <ul class="wai-price-features">
                    <li><i>✓</i><span>Start paketindeki tüm özellikler</span></li>
                    <li><i>✓</i><span>Müşteri CRM</span></li>
                    <li><i>✓</i><span>Müşteri etiketleri</span></li>
                    <li><i>✓</i><span>Otomatik müşteri takibi</span></li>
                    <li><i>✓</i><span>Sipariş yönetimi</span></li>
                    <li><i>✓</i><span>Gelişmiş AI talimatları</span></li>
                    <li><i>✓</i><span>Öncelikli destek</span></li>
                </ul>

                <a href="/admin/register" class="wai-price-button primary">
                    <span>WAI Pro'yu Ücretsiz Dene</span>
                    <b>→</b>
                </a>

            </article>

            {{-- BUSINESS --}}
            <article class="wai-price-card">

                <div class="wai-price-plan">BUSINESS</div>

                <p class="wai-price-audience">
                    Yüksek WhatsApp trafiği ve daha yoğun yapay zekâ kullanımı olan işletmeler için.
                </p>

                <div class="wai-price">
                    <span>₺</span>
                    <strong>9.990</strong>
                    <small>/ ay</small>
                </div>

                <div class="wai-price-limit">
                    <b>15.000</b> AI cevabı / ay
                </div>

                <div class="wai-price-divider"></div>

                <ul class="wai-price-features">
                    <li><i>✓</i><span>Pro paketindeki tüm özellikler</span></li>
                    <li><i>✓</i><span>15.000 AI cevabı</span></li>
                    <li><i>✓</i><span>Yüksek hacimli kullanım</span></li>
                    <li><i>✓</i><span>Öncelikli destek</span></li>
                    <li><i>✓</i><span>Özel kurulum desteği</span></li>
                    <li><i>✓</i><span>İşletmeye özel yapılandırma desteği</span></li>
                </ul>

                <a href="/admin/register" class="wai-price-button secondary">
                    <span>Business'ı Ücretsiz Dene</span>
                    <b>→</b>
                </a>

            </article>

        </div>

        <div class="wai-pricing-trial reveal">

            <div class="wai-trial-icon">✦</div>

            <div>
                <strong>Önce WAI'yi ücretsiz deneyin.</strong>
                <p>
                    30 AI cevabı ücretsiz · Kredi kartı gerekmez · WhatsApp'ınızı bağlayarak test edin.
                </p>
            </div>

            <a href="/admin/register">
                Ücretsiz Başla →
            </a>

        </div>

        <div class="wai-pricing-note">
            Gelen müşteri mesajları kullanım hakkından düşmez.
            Paket limiti yalnızca WAI tarafından oluşturulan AI cevaplarında kullanılır.
        </div>

    </div>

</section>

<style>
.wai-pricing-section{
    position:relative;
    overflow:hidden;
    padding:120px 0;
    background:
        radial-gradient(circle at 50% 0%,rgba(92,255,157,.05),transparent 30%),
        #050806;
}

.wai-pricing-intro{
    max-width:900px;
    margin:0 auto 55px;
    text-align:center;
}

.wai-pricing-intro .section-tag{
    justify-content:center;
}

.wai-pricing-intro .section-title span{
    display:block;
    color:var(--green);
}

.wai-pricing-intro .section-copy{
    max-width:650px;
    margin-left:auto;
    margin-right:auto;
    font-size:14px;
    line-height:1.7;
}

.wai-pricing-grid{
    display:grid;
    grid-template-columns:repeat(3,minmax(0,1fr));
    gap:16px;
    align-items:stretch;
}

.wai-price-card{
    position:relative;
    display:flex;
    flex-direction:column;
    min-height:650px;
    padding:32px 28px 28px;
    border:1px solid rgba(255,255,255,.07);
    border-radius:26px;
    background:
        radial-gradient(circle at 100% 0%,rgba(92,255,157,.04),transparent 32%),
        linear-gradient(150deg,#0b120d,#070b08);
    box-shadow:0 30px 80px rgba(0,0,0,.22);
}

.wai-price-card.featured{
    transform:translateY(-12px);
    border-color:rgba(92,255,157,.25);
    background:
        radial-gradient(circle at 90% 0%,rgba(92,255,157,.14),transparent 35%),
        linear-gradient(150deg,#0d1710,#070c08);
}

.wai-popular{
    position:absolute;
    top:18px;
    right:18px;
    padding:9px 12px;
    border-radius:999px;
    color:#06140a;
    background:linear-gradient(135deg,#7affb1,#4cf294);
    font-size:8px;
    font-weight:900;
    letter-spacing:.7px;
}

.wai-price-plan{
    color:var(--green);
    font-size:13px;
    font-weight:900;
    letter-spacing:1.5px;
}

.wai-price-audience{
    min-height:64px;
    max-width:360px;
    margin:14px 0 0;
    color:#87938b;
    font-size:13px;
    line-height:1.6;
}

.wai-price{
    display:flex;
    align-items:flex-start;
    margin-top:20px;
}

.wai-price > span{
    margin-top:8px;
    margin-right:4px;
    color:#a2aea6;
    font-size:16px;
    font-weight:700;
}

.wai-price strong{
    color:#f3fff7;
    font-size:54px;
    line-height:.9;
    font-weight:800;
    letter-spacing:-3px;
}

.wai-price small{
    align-self:flex-end;
    margin:0 0 5px 8px;
    color:#68756d;
    font-size:11px;
    font-weight:700;
}

.wai-price-limit{
    margin-top:17px;
    color:#87938b;
    font-size:12px;
    font-weight:700;
}

.wai-price-limit b{
    color:#aaffc9;
}

.wai-price-divider{
    height:1px;
    margin:25px 0;
    background:rgba(255,255,255,.06);
}

.wai-price-features{
    display:flex;
    flex:1;
    flex-direction:column;
    gap:16px;
    margin:0 0 30px;
    padding:0;
    list-style:none;
}

.wai-price-features li{
    display:flex;
    align-items:flex-start;
    gap:11px;
    color:#aeb9b2;
    font-size:12px;
    line-height:1.5;
}

.wai-price-features i{
    width:23px;
    height:23px;
    display:grid;
    place-items:center;
    flex:0 0 23px;
    margin-top:-2px;
    border:1px solid rgba(92,255,157,.13);
    border-radius:50%;
    color:var(--green);
    background:rgba(92,255,157,.065);
    font-size:9px;
    font-style:normal;
    font-weight:900;
}

.wai-price-button{
    width:100%;
    min-height:57px;
    padding:0 18px;
    display:flex;
    align-items:center;
    justify-content:space-between;
    gap:15px;
    border-radius:14px;
    font-size:12px;
    font-weight:800;
    text-decoration:none;
}

.wai-price-button.secondary{
    border:1px solid rgba(255,255,255,.08);
    color:#bdc7c0;
    background:rgba(255,255,255,.025);
}

.wai-price-button.primary{
    color:#06140a;
    background:linear-gradient(135deg,#72ffab,#47f08f);
    box-shadow:0 15px 35px rgba(92,255,157,.14);
}

.wai-pricing-trial{
    margin-top:38px;
    padding:22px;
    display:grid;
    grid-template-columns:50px minmax(0,1fr) auto;
    align-items:center;
    gap:16px;
    border:1px solid rgba(92,255,157,.13);
    border-radius:20px;
    background:rgba(92,255,157,.035);
}

.wai-trial-icon{
    width:50px;
    height:50px;
    display:grid;
    place-items:center;
    border-radius:14px;
    color:var(--green);
    background:rgba(92,255,157,.08);
    font-size:18px;
}

.wai-pricing-trial strong{
    display:block;
    color:#eafff1;
    font-size:14px;
}

.wai-pricing-trial p{
    margin:6px 0 0;
    color:#718077;
    font-size:11px;
    line-height:1.55;
}

.wai-pricing-trial a{
    min-height:48px;
    padding:0 17px;
    display:flex;
    align-items:center;
    border-radius:12px;
    color:#06140a;
    background:var(--green);
    font-size:11px;
    font-weight:800;
    text-decoration:none;
    white-space:nowrap;
}

.wai-pricing-note{
    max-width:760px;
    margin:18px auto 0;
    color:#56635b;
    text-align:center;
    font-size:10px;
    line-height:1.6;
}

@media(max-width:980px){
    .wai-pricing-grid{
        grid-template-columns:1fr 1fr;
    }

    .wai-price-card.featured{
        transform:none;
    }

    .wai-price-card:last-child{
        grid-column:1 / -1;
    }
}

@media(max-width:700px){
    .wai-pricing-section{
        padding:85px 0;
    }

    .wai-pricing-intro{
        margin-bottom:34px;
    }

    .wai-pricing-grid{
        grid-template-columns:1fr;
        gap:12px;
    }

    .wai-price-card,
    .wai-price-card:last-child{
        min-height:auto;
        grid-column:auto;
        padding:25px 19px 20px;
        border-radius:21px;
    }

    .wai-price-card.featured{
        order:-1;
    }

    .wai-price-audience{
        min-height:auto;
        max-width:86%;
        font-size:12px;
    }

    .wai-price strong{
        font-size:48px;
    }

    .wai-price-features li{
        font-size:12px;
    }

    .wai-price-button{
        min-height:56px;
        font-size:12px;
    }

    .wai-pricing-trial{
        grid-template-columns:44px minmax(0,1fr);
        padding:17px;
    }

    .wai-pricing-trial a{
        grid-column:1 / -1;
        width:100%;
        min-height:51px;
        justify-content:center;
    }

    .wai-pricing-note{
        font-size:9px;
    }
}
</style>