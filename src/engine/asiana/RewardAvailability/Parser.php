<?php

namespace AwardWallet\Engine\asiana\RewardAvailability;

use AwardWallet\Engine\ProxyList;

class Parser extends \TAccountChecker
{
    use \SeleniumCheckerHelper;
    use \PriceTools;
    use ProxyList;

    public $isRewardAvailability = true;
    private $depData;
    private $arrData;
    private $index;
    private $abck = [
        "44D08FCF1B77CBDE849D655BD99DBC89~-1~YAAQWWpkX+tfXx2SAQAA1ubgJwyRtq5K6rIZ4L2wEQ2EkInytf7r3JxFvcjfZazQ2mb0SuDWAjzLgwoZFgtiRd9EHT7bZvYpRlWwDenqg8GS1lkTdzoPOo1ID4nI1T2D90/NAQf4xbhaDGD0uH7AchB46zm62DlqmvwnKW+1RZQ75RvyVRQ7uh8+4pLVr+/fvM5yF/ONL45hR8N6UZJ0I0kd7PlRzNkfxGcKy+w9jIJgAdD/7sb/B3X/QHMC9G04sJZGkpTlbflhYvp40meMQsuNvXQd+jUPNRvOAJ85hL1VxLlfLic9AFcm2IjxwjZefS7ImAWaNV6zocnK+7Ve8c5yPzpNV4d25aBa/73ZKXngbAusQy2Uxwdc0FpMkoUI8wtm/sAooE0PZ3TR5GJhCjTcpSg9lEv+x2loD9WkbBBrbYYGEuhZXoK5JmTN9LXlW6a4CL9YUZ0aHOq36T6gXaVfCTJ0hQ4ZlmrzgMWS+cxCmjIVpIH4MPyJRdev1B+jl7oHAdRM9MYYpqsY9nMABNJaP+Dz7JWR8owoQRVpi+usmwwupQgN3/bpdx12gMW7Vg==~-1~||0||~-1",
        "14A7196401179B6A68AB44A89B8C45A5~-1~YAAQWWpkXw+UbB2SAQAA8M3/JwyGAxDHhaMGjfrGefl07NXrpHor2pRsCCOvnd7ITpwutO5/8f8QiHvTwQlrfrmMYvZOY+YN2cIBBap0X1RYIAl3JhxE17JpkiaPmXwKWD1OQShR1GVSD3DrSGL8fnfzU9pjOkliezTnVbAQM7Mfcn8NrW/iAgIJhl6SD6mF484lw4xKrWtneB1KfeL6Szcczrj0FqYxSI1Xfn77XDCy625F/vy11AfZPY38FBKh/PSxbt+o82VUouCbe2HBcF1vnj9wHcJ6ToNoUg/BkrTYdASQWgeqJx3LER83oauTV6VFaMS4RrtS2v7pQs5bUGw1abOT8bH0+1/g6TbvQEHDTkFz7Sz+Gl4fBwGgIwXHszBGrW8hQ8umLbxqZoVI5CdWSasw8eHb3PTHxzNr4iA2AFpiSoUNS77nKYeKucDo9f8ZyIpcvks8TQZBbEqJNTOnmVvjZlOGslSeLV6Cu3nhImbVd9KXZiOAi7QwuZlQTWbM7VbM8IexJ/vxh4jP1v5xFIzi/ya8rI5HShR0/5/t2hRkGzAYQ9KB86QMaZ7cZ7fhuSz7wvav+lu0PJI98aQ16lwCgzwaUrshU/zx7J6oZnSdKPfdHKvOyKMJK4uzTjYIawurQicMGbVCx5Wpg3XHPeYJ4VoXWwm5EEUxH/o+LJHB/Qf+RhAdxLc=~-1~-1~-1",
    ];
    private $sensorData = [
        '2;0;3749172;3228464;246,0,0,13,72,0;Ac*$p1]<cX<2?N5(/dvVw7|xugL^2+#nW(MFJk:{KCE1jaG&NjL6tA]<NJk&*j/nl1ajk3WF}~jaSP0h~NdXT+*~&tC;YcklXAkqH7 jA#%L>4NTI thJ|@6G{PwI;fW^2Z?d`7!c{mvivma3]|Lh:z `m5Jl-=+9$_kzdA,x2,39N:=iWk$hCNmlsNf/Gfitd?VjUO7!CYVNl[f?3Lbz}?Rc!WwD^95C)~9Y?*O 5Emp}u+n^@@(uN>Z&P%cs:NgJFr*}aj *w?J;-8&x@e^Tl%TfI!x~K(Yvr)_v>LZt/fa23F3,`&{t3R)Q;`?nMg~p%Tujb~!MfIV{)G52jo|7`tU{kFZID`w6T5pm*BY$Br3kx?5;p=EIw7w~&~q6|Ey)~T277TO%H~UW4Qaba,[0h7xLhHC!^*RflQ~[q@UGdf5u<RksDCKM4PB+{r_B/O*GNLE79e*z@(V:R~3=1m*CxNI0&{yXM#_~8{=T<Y+>#/rKuKPw`M{QkCC+&o$zjH*8gTN[hCvYpuo`#R @0xDlF1!uz}$lxQA^w~g9K2{L*[K]4i CCB^*)_[[6cojqdIkD24buT*#qkZR3o&TXG!T7.()AKXYfGH;M6AoD/W]HK82g[-MRY.eLCY#Zu;1I?6[?qS{Mg)vnymiXKnCJa6ipbxv7Ohv%o#P0@BxZD,22B64)}S-3J3^^ly9^d1.VpZ-k$;nQG7>CdL_UK1ey:rS`^+VdiBg}t$gFf)_JA.LLgb|rjC8AiJ7NGm*VwLo=,C+t6YB% (<sKf*|TTP}93o)/VyKoHmgRWP81fn^_^%-ec}}fbf9QV=Zn6`8XFg8=leIyG@x=5ht9$X}7{lCcb_w886y@1O/>aU0j_`8)V6Jo8Yg x{=y{lFx6QPAr,FxXYW/fU7B,]mC^;Kg]h>zxpDrF|p98gMj?CX!&Jb#7>~l=hU)r&0jdqDm^+!mQ>x=xy(pJOY!E{A$L1Dlj!`PUoFaW4?@tFnRGajZ/EUx5sQSgEc==T8*_X{RT)-(ulX7f{;)ly1b)wL6jU{M]tZWe+m@;  x)iY3y?eK0No9ib(|eStU-7zu3W^rnZdhvUYC%@eWY{2kR: nNCiPw6.~Ly%JB=HTVJV5+Lvxg3L:tpFM/2YId&4+g/w8Q:Ta206)qJu|xAnG70}|eY91P}}2Y*Q_&,9@ZdAnUaFj!EwhKT4;$2su^hnyV&:t)6Nb!#E82^,#h7ccDL@`NKmcKDObDw#t.;;tFIZdiI-+I&]ODMM<;6,<)h#@$Y_aTuh IS!$+^2Lx)f;5>JianlnS4GaJ:?Jt,MF.g*.LSq1H8z~s^!QdspxaPt!w`%KRbBj3c~8R+!Ui^t=Vxm8Yjss_Mr<8-ACw?#HyIr}G>6S t]}qCR~3SSmRN|==:Zs>lO~%,Z?|;<X%xz,FZtN[9;R>KlJ<Kl.e}oG5`CN{ *_+}{EY};n-Vv/%#d7l f5aA-aS3v1Yf+Cyw$DZ&L+U *R]b?m)LYwcBiUkO-M/3F?Zco-RIN,^XYw})iM1R#TpC7(vVNfEUrL1DL&I~M0h8bF9J4x9JvT>$5t{[P{]{6w`& [~tA&[:g<Mdz.]~N4Lwnp/UO--0:>1JT)[G|o=6_:Teb_!<|WQFN=GBE0g*v*3XfL&r[V.oMWJnZY DCp~o%z(z-xVtED9k*z?I37m]E$Dvur<TI9B9DKkodVW SDKT#x= GNPS/2Xt+#&u47cM~u6}sl?dhs2:gHs9GmoZxDG7eL,1t65hzT{z5|ThjL%I>JF/y$g -)J+)Cl;KVngKa#^frgYpyvI-xJrw}w1Anq+Sy9=y_ZpwDgSz,RLhnNU!RCLdQX-t>F=PTsu;f#V| ;ow]DV>LUeFgg8djrK;jpp6j>~XJqtZb-#FR]5@BSjWuC046hd}5KR{:*w_? Io{i{^aR07LoaH}x6$` )wz=}!5&dRU=-5zgzL~q=1^0m975ca56co=k-/ic57Dzcm9UY|/RW;4&RD<3eUv#4!:vHA_/,VX>g%|<|mr!R$hf6ebtuvm]oIafAgYf-EDQe!4V,VrcL}k&Jso6P9?@^tWHnRS39{(FxHBXmq& A OR#O)|wYVUIx?A$PH1fqc}MX$BdTyZEIGHI$]&9%HT_*.g.WO#6N4Ri@YD`y+LUHVxF*<fS#SGFqpqfSdKm=f(OVBn^O`ZGGE4gzb~mlYpi20Ao}fjd(H[R*al_LhW^q;K4daDri .`MuqdP #6~be.Ni;N{f7/pQd`{KK u>?i]M5U|~vIS//7_jRgSOjkqXyuf&1xGweI=0Fvw:h<61KHp<G:flJv/T~RyE9j^^cgJEoOOkr$.0B$hta4FZGKP6K#^L;caeO}E&S^Vlr[zxr8S%dr:u{C4[kI~!q1$7l>NQ#iFF!nvuJV Eq+[^uWI[n[EA@QY{w@6?{g)D//2Il&Wo!VEPSP@e tS5<}%M[qn#cPyL<~KZ)<*@2Dp+I:1MV[CFS8Bn^(5,1y<yf(}<iLH`b9?m?vN%C5Dm:x$kN_~NznCmxtCJ$jd!W1c-TKVGZJNbtNU=W!^6KT{R_X}rbRFwi5zPtiO5W@&XbB_4T0A_^oBx0>e}dE4EUI5Q~69}Ved0D{F~RVGmyymKMW6YGy%fV<&Q/>T2#1#as`&gGv:nx,eXt|)oxiYJ@*{K,l^F19N-CVLF,c.`n~|Y&9i~~15vvk`?sb@NDJc9==,|N<m:&Znex>z]UOTBSBm^g/<KWQ`Kzf?B{o<Q{__Fu9~^fYrarpxJ43v&%<mC(?XNgzmSg<(wV&(^iYrr3,=({?ck`0e4{xc#S>]U>hbi:EB(gco%>4D4A`3i#q=-mjX]F_]0L>h*Gxx`DHfw%5~A@/N|I+ZI>8MZ}Z<4OG$8pA88WF0J,CMLM0po *S[>V A7H<4_JymM8;Fae4Is0J>H8o~8)YeFX]}(1~~[O$vb<LQ|-b}Sa1<9*q.s~q!g/QQE8NmK1Mml]gd7R-A)>ov{EG*L.#*0MMxC93P2!KfXHnk:aG_Dg;A]MHp:5l+)MeF=Pd8hO#SD1Zr?bYrU{Up)/zZ|$bt~Nc2Hjw1*/Lo%!-U+gEvl0x/U^JzOTQxo7f3Z&.K@I~LRO&6j.l&i0}FgllM#z!Ja1P&=}6N*94|p}`xY3#_c2*Acv(qRJNw.c:v6h0TZgmj3HvD5laF>)c5h#4mRji?c.iF#UyASWC%@V0_T94K~<.`/NkL3N|+Y+l!8x=#JV',
        '2;0;3294006;3618371;62,0,0,5,12,0;|8K{)81DD6VZx Dpjac<6&-nK6#)#;{:~pE AL#ZoT$0K`6HaY[4?3S|[fTRQo`y^n>|<K4`VZEY}>||7AQ-w9Z7i-m#t];RAM4z-C3w31]sAp:k&,9wr1L2wu+A>`Fe`7jU <5*+;+.c~[|ZTs]SwE.s}(@Pz/DEJH9b$ur#7c/{Eu9w?C47X~p`~#9R 7s`}j[0VXlv(b9i?}O%6mUt+WKLms:Ja&LPo8%V]ms%S+IFa#-j.u/vqQ!=ZQAtMqJYylsIa/6S|f)1^U1i*7chDx4+0d?]5eTNu<I!fjqBtZ7-`_9|L QDtYfr-Bi1{B-wdh#eZb84j];/A>wg;x{5(t~vTi@A9uH$G~0 rs4q57r#qkCN+Xb*{`y,g/c>?iEvglqXXs(v>C:5yf[ K-Kk^P/0f yZKvwOn/E`7[|d>:7@AC<[(Z|V:8RkUI,Xs9,W@c*yw?bM+7/?6WJQ5&Hpuj6[M}nRW7,SX|P;6rB)|wxA3EOYK;d=?Fm7xe]:K/8vX.ZWc.d_-E6!#BCcIDoFYg7TPA;|3%/g]0ai|N/|{(5!r&0oKAsvkqM#x`a pbgJNJbfyVCA7Ic0BanO/?lT`nN6O!-{=6P7:uQZ*aPAD,+OZ8JvstP<Xzi7:$FLjW}iiO/G(W>EY 3]|Mk<DTA)OmT,7DYVt#559%v9KE9MQ}?v;{&Xl?vh~Gd)4+`h*8Nu]H4Q]RP_6n7sAx<t/BB<R}up~x_W(:Xk|qg0YXIa*c&d6&Lv4gWTsVOP_e)p#.Ta#wwF}+r9T7mU*ra$>[gg4p{h&Dj*t MYUP$W%1ak~pC/aVQt2lskI}--]c7)J]OPst}L4EN.B0ii<FD%U;_hm49A.2]~q9z?UXjM4>T6[F*+kWB.eD?X@.<:y<qE8Z@e9KJ:4>H3jR3Y1)n_[x(I+)>?$J.VA5h1WzM!g}D)`a@@JA%+(PU7phy0oD*W[(~E,XdLC+6cIJ@E9F:Bpf{JM;Xsh*tSq8&Zu7FDQ.[KL;+69?=O/wYkpe5I9)mHN>+Wid>18`F!eni!)@5`3/P#.(N rK9*@!#]C7J>H)MA8MJ[z(3:%}Q94;qD/stdf[%T<gf* nacQba8Yod9z~A/-C:TC):.27J~}K3;C)civ#-Qh;<Pja7w2c$QkATDhinV^Vr$<%]ed->n6Sa%9<t`w<~RQR[]nx:}{^Sd)lPem1Q}6mKRQ]Y6Wj[FQ<c^(0LDK0)3VP@LT%C&;~.g80nj,(~Ty7VV-(FZXH7g/rx5=p2k>~/)z>*+N$jPrw/Gx)QUbvW)CB<esIsZ;kAe(=GBsC8~DTu0g%JOkZc)aW]JCve=pL7a~>DM|IyRb}Hi@R/7]_W3^h7IawXXdT_Mo;4A3Z[T&8|%m4iuYMP!k2)Nvg7#ha3xM$~9rOd]-O/M2^9lx#4r]ftJ*eI93-SX.XZ bGcY4RQdKv^)AZsM0Qi;kbNR1Ux D 3vE16N)P9e]Dp3k!<*rML0eVKSBD-~bCh)tf ^o8CaU/&M+1WZ?fzbD>4_G9:XzazPG.joPvwPP1tL7Hybj/N:C/ytbm90wf+^UKq8|YK/T#fJ1Omu%QhhJL0C;)uJok*&F2yoD=+nhQV6$Bvu$Ry}`r@:Abbba^4ehrAj^KG1I:7pk[sszS/4}.F_g);BB8mQagj|_Ta^UrdM;wOdg~Yu3@Dx4UV9n~U@MS8puYcd|w+n%Q)4KWr!~H|$<j}<7`1x).W5=Roy&)9?-hXn(t[qc5S%Ws-LRl`?ut_B`N^]&LS;a7{1aL8HP ?}:v:Yi!.a|E=z #O_(w.sbA*OvnozBb}T/q)_s.$rZb]G[|26x{A5PB;g1${6-(c<]JtOZIdh~_O<w+Yp(DA*B> (l{!Fe?)lk)]-E.^Z1aLUrbPtc.^v&+3K,UW,L):DqT-uaj`%NY0~tDxUk`YHkfxB2NUw7<N~cv/*eKk~4)(@{-xoVpKn/1KEsu1!0xd^;1o=n>|-qmhr;Y>gU/^ ^auTEYkJsoUIA;| X)6V=,EW$F;`Z-vCr|4Bt/;W4:v,inc_3z1V9@#h;:{Tj#q3p.R98~#PG?r4/x&$YFm;6z;N4]b<3cX=p0[JAq-?mg`olrgvX;i105VtZ@9ckaD#a-ZKM~F6$.+8FdS[B8^aA/^5nUS56dd;,THLC~bO6HIY%.#8& J*+4s<)tjX[N[=:o3BBwa^;Up*2YXy]`@ip `&Ry-1?=ZE{2dUY-BdLti@gfR2;vTi}p#S&UcD2&hKTuFwCHrH;&pJ&+va]|S`aWPbyl)zJJ{UT(q L/#6eIG&zj=&|wgApPV].ZqYLC9g`Hgv!wae^MT?o:{wk$~xDcG:82E{3geTvYPL5}26QC%)~_IH,v>7R1v< na$`k.|H}c}}{}4glp*X[*2Ddq1F4a[bz[NE`_qY@b1pf=sKl,|k0Dr6njQ]yKe$l`*hT&rIM]1r{=e$1Yk/a/f8+<[1f^A@UY4`]ua4Tz,LM!gWl4/ODM66&$CTOHI(=`J/WF[^[u=qc:$HUH>inZL6%jOJ9F5X3^Qt}C$Zx~w|[K6Vjlmm`W+=PGjpDJpVbJj!oM8f!Exh}]9k}bt_dy=*5&.$vcA5g!&M0JI$auj6Czh ?YMj=H ^(iV+==Kq#c6;n},/QvIO_rjeO>A[h%I70ilh]Z{E}0`CA)QW90>S*uf6=8(g?3T(Cf5?NjN%zYw]+,6:z+V!X1mP1h:e} tja&YQ.H#~[F@dym.X.Cxg4_@.M(Y}(3F g~W}js%6ShEDMajcZ.AhT:*Ida`;O4<pL)Iz1{]=.HJ[mdb.9lVt=<(UDxM9@]j[]:F%K^/3,M0I9e]2^!0^C yg&p$+`rpnvU@Z:h<w)P-zxanY^o5ea*@d^>&DEgEBu&U5[?+!vD0:^@.)ExIWr;KZW{Y~f,9duS8;vkNbtz9i~:JaW9_54$H]3[BqOvll+p XzoZy%H|3Ktx6G$`)#zL`:qX,j5t>j^T,cii',
    ];
    private $secondSensorData = [
        '2;0;3749172;3228464;12,97,0,2,3,56;):_gJ0QMk?76ji4&/_RXw?dozg#z7}$mR#LJGf9YOEy1rh;%0noaPeD;SRlZ*r1if2jaj%Z.}.ffYK6|S{.~5(*qTbx}hckBZ9bvsW`cNN;J8=yu*x#%EA71QPqpSCCRW3dk|X3-guhom}b9;a~Xj@ p`:mxmY=0j}cp~7@8!,/<@#?Bp&n!;|M{<  jeDmEv28ZCR0dYn6$NHc`8l.cn^8Qk*W|DOKj-XgP:*Wfq[>S+8kBV*ZreCl*d%PnQ=UoRabo&xbi{7:Yqk0={&&dkJovan1uX|WRstl)TQ8QV{8Vb?8E3=Xh{w=[$I._LsL`wu|X}rbZ*Ma}X!!I:6k@3n[yQiO}6c=j `yg776ET:IT.g|FB:I(*,W>zNuYUka~G^URivxbmFnOi=]x,&p6EV&Y7ueLCy`,PjpU8@Dd-F+p,N8UdsLCAQ95$jQtE#q+8-7-T<:g2tJc6%*05E<s.Rc35_1JG;3]fy0{b!4]+~@mgW[J^v;Tu(vK$!!o~siC|=aVFWmJy`E1oZ*j$A&yJbMi{$y&jo!MCh zk3L,UN*ggZ)v#G?WfkwfCTwXout>@l?4,X-Xo!lw8Q3f-Z_M+Y7.(*AH^U^K={C!y>G)WcPS)CoB(cY<*hVHZRou::F42[rNN{LE+vu!tnSGsCJY/ivDw}7Piw%u)Y-D>$[J,k-:/@m|[&HNiYel}-Xd48TGT-J&@mSQ:;:VXAUQ%E|0rH~DXou,4`{~w_Bf4b$8/LC`P|itH,Aq27LBn2ZvHn49>5xBXIk$0<~gd|+gmVrC3uy9^!KpLnAHWM=[{lRie-)Xd~/3wd3[PCUm6<?]Ah2Rp=EYM@x>;dh=Ow~8{mDij_/O*L52{iq$<8Z+`a8^s;?s6cq&|x=yyqAZ8QL q-u/QUb7bYMn*axJ^3Jfji@wsvIhHyp:;bWqF?L(UcZ.?P5E9m|C>B!kd$LPN0!dQ6t=$}4ll5@ZxTv(T.H^fKeK`W^@8}!jXRE<J^gF?|3ZjA `|$L;?VC~<XqMoEUA2dL0e$62h]1r*SC@nRwY?mbL?*i:;}&o!i[=|<[>:Uv9Hd-xkW[JA4 qHTyhx_pgQ]Z4.(eeU(6wHsyjNHiTrBo$Ynf(F@CQQ=O2wSr~kxG:nxN1/=]#Z.9/^.wevnz!2-4)wKs !KoG.0t}eZF0a(a!U6dw-~CDgXviZ9ZC|JrfGP4L-ssOx7|yV&+w3;Of|#Z<wpJTc3gbToWqdA3KE~%D?)([*yct8N42l@17K45OKR*j`8+4_;#;Tc^eV0e U(R{UiNk jv?;BJ`bE=E|`L:IoD I.$|M`%4A6q8M:w{x2APbutrfPu!zj#!LbCkBX}+Up!SeeM[Yzw@W_q~_XxD4%BN|C!BYPs}?32W$vZ{uDMv*]SvUVs><5ku%h*X/Z?j:42^&(tdC`sUC4AK:ZQ,w#BtybBA&[]3[U]R:gftM:p8pkj0d}j7C>k*e=-AU,i+YgF=)rw=Z0G%S&+UW><r{&OWeG6ndK7U+7;wPhkkN$DjY4O~%2dQWeOzk@;{vILpHQqGFB/wD~O0eCa7A2.!,K~]>0ux%VM!fw-qa!&[$glQFGg<OhPLUOY.,Fvjm([Y;Zs<-F[__|QxCkjCX@83P:#[#FPu#rJ&d6zF8TB!.q1$^uOfLtea+@8l p%z+&2jVtOtDk=m|C}J;>3]B8lb+UE5MLQM{U8)DN(?Z-Lx`_SvXcr]w(BKyNR7?8B9/{/%q%#-+53#^cBV0A:uS}e+t:>-P1b$2:VH>{R>sFzte]LhMeFElSdnC&&,<NkRBIai_M+:H$Y`HQPd``_]-k~9vXfa&7`~FVouxrUkjU86flCPNtcmB%6^}-B0;oAd<X|w/n6x;!<AB}x=BkZ^J/]k=>/a8H5PZ7lZ({Z>O#u@2+Ks$BJMpk*zn[u#UvTqc MXvgo0Y>1@`uaCcX6lb-Lvk:$l=4T}e(X85bQ%-QsT_UNt ObKkp1lg6UB I^:[$YPG6A?rl4_lb(/Gn$A@?HMMQ~u|^8zf>|7ezj^2u,If6R5c2>]_l(!:.h%yEV#Z?5a~@N1`iU8[!S*2AWn&,wTc`;kX76jPK=lQ/1(s59N>v44YxO(4I$Q2AiO=.|o XSrXd|w[{&NmV9sC,Z;:SJ7[Y>bhTLqVKW4sKd@jD09y}zc@~ETD#cD2W)p%=I{^Q.Gb&VU-DrkNK!>|/=n`Z9*r`!,q.ogRqd9>}Qs0L}c*@ZVF..vSm{BhOv^RZi.RE}H`Tk[(@h@JdvvYjZ>hF5,Dyq3h49x2f[%/m8=Lu(FF0 $;>*swQaLM&z<jS|olPsMu|(Ie`WKySok_NthO%(K}bf,!&.lm3P_!q~ip?iamx3rp@7^?I@iwgmTt]r;%}`4O46{Et:W&GaZn kNO&:Bn(B9bs~Bqii)!O(),`y]DwsRBWi}$h`~egn^}nAUXS%.6x:QJw{rCX~l [;A@xa)o?senq-?Esv-{JK$QX1%UC>Ncndb,&!%;<oqKi5E|Qr,~y+j-fY04)rNR}7aVV};yuWeK/@y`LKc xX)]N7N 38uw+/0`49cW[EuWlt>*>c/-H0Y@8f.EU 9]QO]](*:!hFF/VqWmKfP)6W]v)|)z Y:j8Z4+!+`vvl5T[/*E=NcY,wuZRn5<<mf|]iK@_AvNKu$~{ eGB-vInA25Anm&@It6iz{M^ _&Y~n~J2{dN}rNe%#U;CFXiS79xgc:)+u]n:~l<hD6SKga)13~KR=jgC={sE}6W^N}9#hiUq[RjuJ*#qX]W,9$uXKC|N#g?24R%woq=rQ-#0&U6pf`)a>!y[WJC/p7ablDi]RXkV$<0Pu6i3@@v2BtLXmN;3+Q#-@l?E6NNex?q1%J}][kKog0MNP+W97o:%Qnmo]!,Vm)0hz5^[9hRpsv]|7S]!KGt6A-e}/ak8:cJc_:D6&4#]fRe_&%=z6d,Xln!KWu4b%Yf2?B tsDBjvl*2G%jhfJ:MmfYje1J)K(:fm#vf!K6%$*NVn{/?.1!Bf4CfeFG?hDk6 cMH#Btl4,YY~3Zax^0*K@=AqL]LLX!Ou,5zQ|uc|)NkwHwv1*(L(,{?M7II$g+t/XXO{STWqw7]3S%. BAuL/V(6*VBvpu}Dglt%#*vFX(W-Fu/NUR1wpzUz`2#_c5)8my0h%QOw0E1z6n8`UgD)8=PClF<wo^=iIY,@1mqw6k_V|4yMRWI.HV0bS0I|_[Z,^s2r1SP8&W+q/j/]5luY&<8e!(:*%i/mOk}gVv!a#:#yAKga~8,w=Y7+Y_tYnaf8+KKZUV%k+/_b3&K7T|sI|xmENHt~*%!bGyy?BSt>42fHD=%`z@GB]>~Dkj(!1..UT?nast]Dz#gnDZ1T*cbi6kZMCQsy?/+2*g.14f05gKO5%#0vF%$#g/,{9=gE/-@w`7#F54cME@4;s=(wP~bA)PXejZ6`yU5#.DSXnJZvjr{#Vxm`Mc:J&wzP*9t#y/wt oy`G^9;OA5zy]~Z;x!}9yK ~20OM^,]@&c[n4oloV$GH5*^y#v>{g .(fZa{K*7I.7A@Qs;7Og1:SRUGvYzkdl-StOvKu)TxdOsU:_)4^t<:kn&n^%TQMUp=t7wf~:q8#%G-wis#I}k1JSd|;/L{d1jlG#NZ=XgyI_y.G;',
        '2;0;3294006;3618371;37,73,0,4,7,75;!yK{3;o>M:S[%.@nekc<PD7gCp)$v1!7}lI{; )YAX%!L@2>{ab4)2W||bSRJf[~=n>)GRAs]inc#7u$A@O-w:01iqj  >9*FL3u,&V}/+^nriBL#dc}g+-6rz!A=`fcG3ew`)k_kle+e~QqzUxgE.~p_en+]%]IGG:C=iVHKd;JO$xBz#9;:Av2x:AYQQ;]X#re+{[Oj)`3l?`y0:aZ_,[KU`not/<fMQ4!WdV0Nn/>l,&S+Jvox20}*EW|Es=ty5/=oIJK|6(QJ~vP?Sz&l3FIFu/](?F]tlsS$]UKyI-}=62l[IqBt];>B0Q-U>k9d7>L#hpt}XS/<{<|jDv!7y.{~J>1RFIu<r*3(g{9sB7w,tkCQ*ao)XEN:`*h7VlOrD`v9]nVsF<1@}.}uP:Bl_Px5i/tO+ByBs+FF0S#eH96:9&b?y_[q@8RkR(#]Tg&`6a~sQikH+-4?6uDW5.Rnmf=]>}nWT?9eY!L=6HJ0zNxj8|NR{ed;9vh1J_/rP72C0.(]l9idZ4c}$xDdDyCJ[86]$6oV_P^n])gC~N+S 01Yj&1)DApTexIvq`4]lbg.t.Up#cI4=Ug75kz=56qPdnSp.t1,<2LB:jPg)eDvDk|Yg@Wo-wZ8]s37B#GPl_yomU5Qo^=P[t,]}QrB>,A1Hd4U=9O[sL5=4%m1PK3BZ*CrTv1Tm-s_&Cb#>%WCH7NWZ?Xm2w3.@m7|C|8melE/+(yk~!HV$D/jvA9362P=,-*@= {Nia%`0[&O5j/$LgZ=(v|HyUxB,Ao[TwF&>hol4lqh(@f.UOHbyxyS*-@f$l.KN2eRYC=A$e{`8B};&Y1Jot@S9Al*L,ed<3qPtVdhK(CIq3]$l?s72^fS0JU3fG$-deG0k>?[;3>F /r%9ZD@7Eo=33M,^S8?*$N`5p(Ie&3t{H)V ya,gz&xJIG$UX@AyB%1kPcv0f%,e>*ac4*E,A[LDB/cJ}:EiF;7yb#FPCXxe6}S*5%ZASuiq.`K3r&66D<M+{|0qe2N5&hRG6e]Jd97>e2(inck!9CE,JW*!~2Gq@?2@wKaGncEKyJFCBC[!(4:Y|T18:h7%I3af[@rFg9OR9Rd2aaX:mdxtzA*bCyHG$J[io!z~L(4R)cpg#-Ua38PPa8!.F$^Y:O(0hc{a:fI?0[fh->.0Ya+8B}`wr?]M|Tem::${^Sf-lQEm*|x>i*MQ]-5S1_EFCd^U&PDl,(3ZL?Ma/8S|OgGoXrn,SB}73_c6(IU^Q33&zs+=w<f&x5vx5++IyjUuk/L|.ONld]-Cw?hsN{gznDq0=EG{I1UAOq1mzFTCYU^a`X.C$S6nU%a#IKT|EV*.%=Hs.m7!a]*9Hog+>y`O8dSl?8B)bij,Iy2{=m}PRT9s!$W*e~$iF]0[=HeT1,[oK_j,xNs)0l30n04i`m8VO8bnfYha$smOz3aw..l&nV.~0~6Rxn48X<zsD{8)3Ml49U*H)>r #yJ&j~^;Ve}J1sI n+(=2Sc}zP3t2`crK16d_R=(Asy h~qF}GDGW-9Pdja_n8Ow$%<@P2P(BXc:[82{~)wfJ>i1+3bJ!=^hvRM@5&-/=0boE-A+ln-SeV.z9gp1y?c+Y$NtuZ)0&8L*_}<kp%`}ru0,H+.Up:_/A(2cs^CVl|iWhB[(>@(0HdxK]<rq0iGCQsd#6]ljed0]=nI+ vtC3QI+C0bz5oS608OPaIGIP|/;C3;W1g|CfK *iqNNl!sPK.Ql@)T3X)tfH?T/OYc4<th@/`{bI/P.v^TvVvbkV exyq&J*13h*Rxnh_ 4}_jpk.yA{O=^!1Xb6zIU56S;2rf%#d9dx!P$yWc`dEWy=m;,*OiKqOTyTyn?*?2!K^hs]8*jS~2<s})f[zr*E d9]?JRFV9=J6,N!F&Q?_*qNG$Al,9EOFgZ6`S1dIpK1l!i#7]GfW#EcZ{]9rv8Hl-e3SL!-W|qT1voR!6PYL|bf9s!4SFJ*st4Q-KSqGnO9$_7CH5`_4e?M,$UL]<Qr@C.1n*COa#`O={N`QYKSq`Arcnk[gA[!p?,_~6}G^B,52v7&I(;$z~?T?JnOULtP2R/Y:#MCfJNnE#wU0 e=RR-xPamgdpB<j1P1UtZvWmjxG.]&0PF8 ei<}9DVtcH8Y>549-h)W761_kPzTTPzVK@PUc],0@4zKh+,brMq`T;sa2Q`3-<NuQ3R;!$HYhXV5l*.Z7$lumT;~ds/ig$nHHDNW+gav*{0eJhvLx|nxw$!mI$M/@ZN$^(NTD>xw7?I2v#,a2jZgx??YJ goIfG56f+~F>g|/x`B:Qm-5DD``+}c.=!ffH:~<MmQf3WuHLcSUs8%^wt)Ad)156W[d84H]dAFdm@g{U_aHx)d09<bnuxvp/9z<^aj{=cWp>~NwB]VQ%SH;O`&7yZ(:{_b7snY&F!L8da|8w<Bo$E|rc:v*0)%.yML~a7j*W1]R+LD1RG{)6iGm3wthyzA3yV{jgJUUqZa+-,bIq>p_@u35SwNyW axBKW2o#0,:!WTC8}qqLeew@K`sd#N<Sp `^$A|<ETwk[R?Lj[$]@ i^z!.F&RBtk.]u+!pz2_sQ(b,2tqQ,>kUCauH&h?8e^+dt5Y}>d;{qqviya#J/BtBz!50BBqlJ?uXe-jAPi/w}z,KKH lgtJ?5ps&9;vC7skaP3@}nL8(2.RB_SCNN]A=:s/yQAAt }ZJvI2B!M;<b;Z!zFSB=}=HODOIr}{&8jTnlIQPh`9&q:5qbcRmhNJU?uC4sT(1J^ue>j~0o?xNf5b>kx8].yufwR(Nnys3.8ttM*DY[x*|BP!@awBC?#e,1|/[PbbrS-u+U_Y|]BVV(3ne_g7wT9%9Z?&4c?B_g(.&aNh*(gR~OqQ.U!H,DTm/I7}KRlO:LfcN]Lq/fx7>L=~4WDyxA2&3T>hfo(Bp-GFY0ch0/}H]k[:=S~Mo.|(K!{c{xMm0By{=0{{0-uMh>lX =jYGeXYed`!z6QD-j<<`Y[x3wjUWIiX3*;@c-)=LhRHucWoOrF`,*l)IQW&AOuh<*?O|ESCJ&)2[yKvV$`oExn6m!/q7}eZIDmy6el,GI^koP,e!Bf|>TfB9?bAtYFD/6j`<s1@lAVl=,IA4_PuL_oOF_#^::t#dj^R)q^F4|CT3QJooD4a0NC}Pi6zQGFXGW;S~crDI;^eDNm;CulM0HXT&><!hiK7TUNL43d,4.IdDMb<80bI5Kr{.{diOKBuz_#{~R,Upd>c870yyr[W;M| @1_NFNfLp*KPQgc61<`TQssQ)m|XQvD{Di>HxIHl|JXQ[D.Qr+ %yM{-dcWL8,#aYd8>P1?[p6a.es66SdPziZK,yi&Sk;)0h9rlt;@w$Lby&fpSoPbE9hPPW^oskH.wbG2LOoo%c<Mn % e|5 :bmvzydYKe=++cSzC68Cpwv>ZoQq7^R6y WCsErcdybRGAcOfIR@Cw qJNmW wL3{>h/f:>7=!tl[dKG>Q#wj&BcotAc2j0-x=:nuOf.2OCEw@>|}#I|sB,H7T{qhBX?q$`a(Pw;~XDFHrt*HlJtl:0^Fg-@<7LTCp)RwTuIe,*%dNU<{EznPW8eFwIP[A!!/IjKfGrKr7;E$cv{]$lH$M(`]&TVl.@@T}>tK<eJc5/E8&*7uKB{v1gV@3Lepk4*|@?P(p9{UV;QL! _?9B)J|Eh6]Beuqq34eEi!qxIpr5eO V^gXCA}pm2Z=&Fum`p4KA1oAe>X@[!usA_9%e$,!zW9k]8M@dl2dBC xXI/u:^TP|O>(0E8>JlSE$_ ^&YvjJ;26nkF1[,bQi+${,&>F4^auM1OZ@_iM-<#(klPKqIQ_3miKjw76YBs]dyA)1626mIryo~A]~`xrY50;FPr(ZR}n|_$AQ*ZSFWVl[4^X%m]+r{YdlTTm.?`dIV:,cy[`E]dt|~nHa:PgR@QkFb)MtaH[YN7TiQ0<3POh15,)f_v5dm}+5?HbhX+0C0~KfaBQT]A4FWPlXC_3&q-(C^<,^~Xn)K=7TRE$O>|SaS36^PG?pg~4=5te{}U:PvpIHi_mGmV,@k=%&t#Ho?.*V f<%//SI3}=~yv92exTvAVb7i<mwxtZLwB>9]8[oPAoDI)2$PcnM 9F^l_6=Fq-zr9_Y@a7_tyqO{B&(`$%VV8LkZO&M[1aA+/d^Q6jH5N,!5=R%7!bE#DnsWpNat_%yX(;F&7I%-TA+EDuB!N>HY$DX)',
    ];
    private $userAgents = [
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/128.0.0.0 Safari/537.36',
        'Mozilla/5.0 (X11; Linux x86_64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/100.0.4896.60 Safari/537.36',
    ];

    public static function getRASearchLinks(): array
    {
        return ['https://flyasiana.com/C/KR/EN/index'=>'search page'];
    }

    public function InitBrowser()
    {
        parent::InitBrowser();
        // блочат на Illuminati  и DO
        $this->index = random_int(0, count($this->userAgents) - 1);
        $this->http->setUserAgent($this->userAgents[$this->index]);
        $this->http->setDefaultHeader("Accept-Encoding", "gzip, deflate, br");

        $this->setProxyBrightData(null, 'static', 'kr');
//        $this->setProxyMount("NJ");
        $this->http->setHttp2(true);
    }

    public function IsLoggedIn()
    {
        return false;
    }

    public function LoadLoginForm()
    {
        $this->http->RetryCount = 0;
        $this->http->GetURL("https://flyasiana.com/C/KR/EN/index");
        $this->http->RetryCount = 2;

        if ($this->http->Response['code'] !== 200) {
            if ($this->isBadProxy() || $this->http->Response['code'] == 502 || $this->http->Response['code'] == 503) {
                $this->setProxyGoProxies();
                $this->http->RetryCount = 0;
                $this->http->GetURL("https://flyasiana.com/C/KR/EN/index");
                $this->http->RetryCount = 2;

                if ($this->http->Response['code'] !== 200) {
                    if ($this->isBadProxy() || $this->http->Response['code'] == 502 || $this->http->Response['code'] == 503) {
                        $this->markProxyAsInvalid();

                        throw new \CheckRetryNeededException(5, 0);
                    }
                    $this->sendNotification("!200 // ZM");

                    $this->markProxyAsInvalid();

                    throw new \CheckRetryNeededException(5, 0);
                }
            } else {
                $this->sendNotification("!200 // ZM");

                return false;
            }
        }

        if ($this->http->currentUrl() === 'https://ozimg.flyasiana.com/error/error.html') {
            $msg = $this->http->FindSingleNode("//p[contains(.,'We apologize for any inconvenience.')]");

            if (!empty($msg)) {
                $this->logger->error($msg);

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
            $this->sendNotification("check message // ZM");

            return false;
        }

        if ($this->http->currentUrl() === 'https://ozimg.flyasiana.com/access/pc/noticeOfTemporarySuspension.html') {
            $msg = $this->http->FindSingleNode("//p[contains(normalize-space(),'Please understand that our service will be restricted due to conversion') or contains(normalize-space(),'Notice of Temporary Suspension for Web site/Mobile')]");

            if (!empty($msg)) {
                $this->logger->error($msg);

                throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
            }
            $this->sendNotification("check message // ZM");

            return false;
        }

        if ($msg = $this->http->FindSingleNode("//p[contains(.,'Asiana Airlines is undergoing a regular system maintenance every Sunday to provide stable internet services')]")) {
            $this->logger->error($msg);

            throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
        }
        $this->sensorSensorData();

        return true;
    }

    public function Login()
    {
        return true;
    }

    public function getRewardAvailabilitySettings()
    {
        return [
            'supportedCurrencies'      => ['KRW', 'USD', 'AUD', 'SGD', 'GBP', 'JPY', 'HKD', 'EUR', 'CNY'],
            'supportedDateFlexibility' => 0,
            'defaultCurrency'          => 'KRW', // !important
        ];
    }

    public function ParseRewardAvailability(array $fields)
    {
        $this->logger->info("Parse Reward Availability", ['Header' => 2]);
        $this->logger->debug("params: " . var_export($fields, true));

        if ($fields['Adults'] > 8) {
            $this->SetWarning("you can check max 8 travellers");

            return ['routes' => []];
        }
        $settings = $this->getRewardAvailabilitySettings();

        if (!in_array($fields['Currencies'][0], $settings['supportedCurrencies'])) {
            $fields['Currencies'][0] = $settings['defaultCurrency'];
            $this->logger->notice("parse with defaultCurrency: " . $fields['Currencies'][0]);
        }

        if (!$this->validRoute($fields)) {
            return ['routes' => []];
        }

        if ($this->depData['mgtArea'] === 'KR' && $this->arrData['mgtArea'] === 'KR') {
            $redemption = 'RedemptionDomesticFlightsSelect';
            $redemptionAvail = 'RedemptionDomesticFlightsSelectAvail';
            $domIntType = 'D';
        } else {
            $redemption = 'RedemptionInternationalFlightsSelect';
            $redemptionAvail = 'RedemptionInternationalAvail';
            $domIntType = 'I';
        }

        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'           => 'https://flyasiana.com',
            'Refer'            => 'https://flyasiana.com/C/KR/EN/index',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        $postData = [
            "segDatas" => json_encode([
                [
                    "depArea"    => $this->depData['mgtArea'],
                    "depAirport" => $fields['DepCode'],
                    "arrArea"    => $this->arrData['mgtArea'],
                    "arrAirport" => $fields['ArrCode'],
                    "depDate"    => date("Ymd", $fields['DepDate']),
                ],
            ]),
            "tripType"   => "OW",
            "bizType"    => "RED",
            "cabinDatas" => json_encode([$this->getCabin($fields['Cabin'], true)]),
            "domIntType" => $domIntType,
        ];
        $this->http->RetryCount = 0;
        $this->http->PostURL('https://flyasiana.com/I/KR/EN/BookingRestriction.do?n$eum=200859595093156450', $postData,
            $headers);
        $this->http->RetryCount = 2;

        if ($this->http->Error === 'Network error 56 - Unexpected EOF') {
            $this->http->removeCookies();
            $this->http->GetURL("https://flyasiana.com/C/KR/EN/index");

            $this->http->RetryCount = 0;
            $this->http->PostURL('https://flyasiana.com/I/KR/EN/BookingRestriction.do?n$eum=200859595093156450',
                $postData,
                $headers);
            $this->http->RetryCount = 2;
        }

        if ($this->http->Error === 'Network error 56 - Unexpected EOF') {
            throw new \CheckRetryNeededException(5, 0);
        }
        $this->http->JsonLog();
        $sessionUniqueKey = $this->generateUUID();
        $passengerConditionDatas = [];

        for ($i = 1; $i <= $fields['Adults']; $i++) {
            $passengerConditionDatas[] = ["passengerType" => "ADT", "passengerTypeDesc" => "Adult"];
        }
        $postData = [
            'bookConditionData' => json_encode([
                "bizType"               => "RED",
                "tripType"              => "OW",
                "domIntType"            => $domIntType,
                "userData"              => ["acno" => "", "familyNumber" => ""],
                "mixedBoadingLevel"     => 'false',
                "segmentConditionDatas" => [
                    [
                        "departureArea"        => $this->depData['mgtArea'],
                        "departureAirport"     => $this->depData['airport'],
                        "departureAirportName" => $this->depData['airportName'],
                        "departureCity"        => $this->depData['city'],
                        "departureCityName"    => $this->depData['cityName'],
                        "departureDateTime"    => date("Ymd", $fields['DepDate']) . "0000",
                        "arrivalArea"          => $this->arrData['mgtArea'],
                        "arrivalAirport"       => $this->arrData['airport'],
                        "arrivalAirportName"   => $this->arrData['airportName'],
                        "arrivalCity"          => $this->arrData['city'],
                        "arrivalCityName"      => $this->arrData['cityName'],
                        "cabinClassList"       => [$this->getCabin($fields['Cabin'], true)],
                    ],
                ],
                "passengerConditionDatas" => $passengerConditionDatas,
                "searchCurrency"          => "",
                "childOnly"               => 'false',
                "parentPnrAlpha"          => "",
                "mobileFlag"              => 'false',
            ]),
            'sessionUniqueKey' => $sessionUniqueKey,
            'mainQuick'        => 'true',
        ]; // "E","R","B"
        $headers = [
            'Accept'       => 'text/html,application/xhtml+xml,application/xml;q=0.9,image/avif,image/webp,image/apng,*/*;q=0.8,application/signed-exchange;v=b3;q=0.9',
            'Content-Type' => 'application/x-www-form-urlencoded',
            'Origin'       => 'https://flyasiana.com',
            'Refer'        => 'https://flyasiana.com/C/KR/EN/index',
        ];
        $memMax = $this->http->getMaxRedirects();
        $this->http->setMaxRedirects(0);
        $this->http->RetryCount = 0;
        $this->http->PostURL("https://flyasiana.com/I/KR/EN/{$redemption}.do", $postData,
            $headers);

        if ($this->http->Response['code'] == 403) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $this->http->setMaxRedirects($memMax);
        $this->http->RetryCount = 2;

        if ($domIntType === 'I') {
            $script = $this->http->FindSingleNode("//script[contains(normalize-space(.),' bookConditionJSON =')]");

            if (!isset($this->http->Response['headers']['location']) && empty($script)) {
                throw new \CheckException("something other", ACCOUNT_ENGINE_ERROR);
            }

            if (isset($this->http->Response['headers']['location'])) {
                $redirectUrl = $this->http->Response['headers']['location'];
                $this->http->NormalizeURL($redirectUrl);
                $this->http->PostURL($redirectUrl, $postData, $headers);
            }
        }
        $curUrl = $this->http->currentUrl();

        if (!$script = $this->http->FindSingleNode("//script[contains(normalize-space(.),' bookConditionJSON =')]")) {
            throw new \CheckRetryNeededException(5, 0);
        }
        $bookConditionJSON = $this->http->FindPreg("/\s+bookConditionJSON\s*=\s*JSON\.parse\('(\{.+\})'\),/", false,
            $script);

        if (!$bookConditionJSON) {
            throw new \CheckException("no bookConditionJSON", ACCOUNT_ENGINE_ERROR);
        }
        $this->http->JsonLog($bookConditionJSON, 0);

        if ($domIntType === 'I' && $fields['Currencies'][0] !== $settings['defaultCurrency']) {
            $selCurrecny = $this->http->FindNodes("//*[@id='selCurrecny']/option/@value");
            $varCurrency = [];

            foreach ($selCurrecny as $cur) {
                if (preg_match("/^(\w+)\/([A-Z]{3})\/([A-Z]{3})\/([\w_]+)/", $cur, $m)) {
                    $varCurrency[$m[3]] = [
                        'officeId'    => $m[1],
                        'pointOfSale' => $m[2],
                        'paymentType' => $m[4],
                    ];
                }
            }
            $this->logger->debug(var_export($varCurrency, true));

            if (!empty(array_diff($settings['supportedCurrencies'], array_keys($varCurrency)))
                || !empty(array_diff($settings['supportedCurrencies'], array_keys($varCurrency)))
            ) {
                $this->sendNotification("new supportedCurrencies list // ZM");
            }

            if (isset($varCurrency[$fields['Currencies'][0]])) {
                $bookConditionJSON = preg_replace(
                    [
                        "/(officeId\":\")(\w+)(\",\"tripType\":\"OW\")/",
                        "/(,\"searchCurrency\":\")([A-Z]{3})(\",)/",
                        "/(,\"paymentType\":\")([\w_]+)(\",)/",
                        "/(,\"pointOfSale\":\")([A-Z]{3})(\",)/",
                    ],
                    [
                        '$1' . $varCurrency[$fields['Currencies'][0]]['officeId'] . '$3',
                        '$1' . $fields['Currencies'][0] . '$3',
                        '$1' . $varCurrency[$fields['Currencies'][0]]['paymentType'] . '$3',
                        '$1' . $varCurrency[$fields['Currencies'][0]]['pointOfSale'] . '$3',
                    ],
                    $bookConditionJSON);
                $this->logger->debug("updated bookConditionJSON");
            }
        }

        $headers = [
            'Accept'           => 'text/html, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'           => 'https://flyasiana.com',
            'Referer'          => $curUrl,
            'X-Requested-With' => 'XMLHttpRequest',
        ];

        if ($domIntType === 'I') {
            $postData = [
                'domIntType'        => $domIntType,
                'bookConditionData' => $bookConditionJSON,
            ];
        } else {
            $postData = [
                'bookConditionData' => $bookConditionJSON,
            ];
        }

        $this->http->PostURL("https://flyasiana.com/I/KR/EN/{$redemptionAvail}.do", $postData, $headers);

        if ($domIntType === 'I') {
            $avail = $this->http->FindSingleNode("//table/@avail");

            if (!$avail) {
                if (($msg = $this->http->FindSingleNode("//div[@id='avail_Area1'][contains(.,'There are no flights')]//div[contains(.,'There are no flights')][count(.//div)=0]"))
                    || ($msg = $this->http->FindSingleNode("//div[@name='avail_Area'][contains(.,'There are no flights')]//div[contains(.,'There are no flights')][count(.//div)=0]"))
                ) {
                    $this->SetWarning($msg);

                    return ["routes" => []];
                }

                throw new \CheckException("no avail", ACCOUNT_ENGINE_ERROR);
            }
            $data = $this->http->JsonLog($avail, 2, true);

            if (isset($data['errorCode']) && !empty($data['errorCode'])) {
                $this->sendNotification("some error // ZM");
            }
            $data = $data['availDataList'];
        } else {
            if ($msg = $this->http->FindSingleNode("//div[@id='emptyAvail'][contains(.,'There are no flights')]")) {
                $this->SetWarning($msg);

                return ["routes" => []];
            }
            $availDataList = $this->http->FindSingleNode("//input[@id='jaAvailDataList']/@value");

            if (!$availDataList) {
                throw new \CheckException("no availDataList", ACCOUNT_ENGINE_ERROR);
            }
            $data = $this->http->JsonLog($availDataList, 2, true);
        }

        return [
            "routes" => $this->parseRewardFlights($fields, $data),
        ];
    }

    private function isBadProxy(): bool
    {
        return strpos($this->http->Response['errorMessage'], 'Operation timed out after') !== false
            || strpos($this->http->Error, 'Network error 28 - Connection timed out after') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 407 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 503 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 522 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 400 from proxy after CONNECT') !== false
            || strpos($this->http->Error,
                'Network error 56 - Received HTTP code 403 from proxy after CONNECT') !== false
            || strpos($this->http->Error, 'Network error 56 - Unexpected EOF') !== false
            || strpos($this->http->Error, 'Network error 56 - Proxy CONNECT aborted') !== false
            || strpos($this->http->Error, 'Network error 35 - OpenSSL SSL_connect') !== false
            || empty($this->http->Response['body'])
            || $this->http->FindPreg("/You don't have permission to access/");
    }

    private function getCabin(string $str, bool $asianaCabinCode)
    {
        $cabins = [
            'economy'        => 'E',
            'premiumEconomy' => 'E',
            'business'       => 'B',
            'firstClass'     => 'F', //'R'??
        ];

        if (!$asianaCabinCode) {
            $cabins = [
                'ECOBONUS' => 'economy',
                'ECOBONUSP'=> 'economy',
                //                '' => 'premiumEconomy',
                'BIZBONUS' => 'business',
                'BIZBONUSP'=> 'business',
                //                '' => 'firstClass'
            ];
        }

        if (isset($cabins[$str])) {
            return $cabins[$str];
        }
        $this->sendNotification("RA check cabin {$str} (" . var_export($asianaCabinCode, true) . ") // ZM");

        throw new \CheckException("new cabin code", ACCOUNT_ENGINE_ERROR);
    }

    private function parseRewardFlights($fields = [], $data): array
    {
        $this->logger->notice(__METHOD__);
        $this->logger->info(
            "ParseReward [" . implode(
                '-',
                [date("Y-m-d", $fields['DepDate']), $fields['DepCode'], $fields['ArrCode']]
            ) . "]",
            ['Header' => 2]
        );
        $routes = [];

        if (count($data) !== 1) {
            $this->sendNotification("availDataList 1+ // ZM");

            throw new \CheckException("new format", ACCOUNT_ENGINE_ERROR);
        }
        $data = $data[0];
        $dataFiltered = array_filter($data, function ($s) {
            return !$s['soldOut'];
        });
        $this->logger->debug("Found " . count($dataFiltered) . " routes");

        if (count($dataFiltered) === 0 && count($data) > 0) {
            $this->SetWarning('All tickets sold out');

            return [];
        }

        foreach ($dataFiltered as $numRoot => $route) {
            $this->logger->notice("route " . $numRoot);

            $this->logger->debug("Found " . count($route['flightInfoDatas']) . " segments");

            $stops = 0;
            $segments = [];
            $totalFlight = null;

            foreach ($route['flightInfoDatas'] as $segmentRoot) {
                $stops += $segmentRoot['numberOfStops'];
                $segment = [
                    'num_stops' => $segmentRoot['numberOfStops'],
                    'departure' => [
                        'date' => preg_replace("/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d+$/", '$1-$2-$3 $4:$5',
                            $segmentRoot['departureDate']),
                        'dateTime' => strtotime(preg_replace("/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d+$/",
                            '$1-$2-$3 $4:$5', $segmentRoot['departureDate'])),
                        'airport' => $segmentRoot['departureAirport'],
                    ],
                    'arrival' => [
                        'date' => preg_replace("/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d+$/", '$1-$2-$3 $4:$5',
                            $segmentRoot['arrivalDate']),
                        'dateTime' => strtotime(preg_replace("/^(\d{4})(\d{2})(\d{2})(\d{2})(\d{2})\d+$/",
                            '$1-$2-$3 $4:$5', $segmentRoot['arrivalDate'])),
                        'airport' => $segmentRoot['arrivalAirport'],
                    ],
                    'flight'   => [$segmentRoot['carrierCode'] . $segmentRoot['flightNo']],
                    'airline'  => $segmentRoot['carrierCode'],
                    'aircraft' => $segmentRoot['aircraftType'],
                    'times'    => ['flight' => $segmentRoot['flyingTime'], 'layover' => null],
                ];
                $stops++;
                $segments[] = $segment;
            }
            $stops--;

            if (!is_array($route['commercialFareFamilyDatas'])) {
                $this->sendNotification("check parse availDataList // ZM");
                $this->logger->error("skip route {$numRoot}. no offers");

                continue;
            }
            $this->logger->debug("Found " . count($route['commercialFareFamilyDatas']) . " offers");

            foreach ($route['commercialFareFamilyDatas'] as $offers) {
                foreach ($offers['fareFamilyDatas'] as $offer) {
                    $segments_ = $segments;

                    foreach ($segments as $num => $segment) {
                        $segments_[$num]['cabin'] = $this->getCabin($offer['fareFamily'], false);
                        $segments_[$num]['classOfService'] = ucfirst($this->getCabin($offer['fareFamily'], false));

                        $segments_[$num]['fare_class'] = $offer['bookingClass'];
                    }
                    $result = [
                        'num_stops' => $stops,
                        'times'     => [
                            'flight'  => $totalFlight,
                            'layover' => null,
                        ],
                        'redemptions' => [
                            'miles'   => $offer['paxTypeFareDatas'][0]['mileage'],
                            'program' => $this->AccountFields['ProviderCode'],
                        ],
                        'payments' => [
                            'currency' => $offer['paxTypeFareDatas'][0]['currency'],
                            'taxes'    => $offer['paxTypeFareDatas'][0]['totalTax'],
                            'fees'     => null,
                        ],
                        'tickets'        => $offer['seatCount'],
                        'classOfService' => ucfirst($this->getCabin($offer['fareFamily'], false)),
                        'connections'    => $segments_,
                    ];
                    $this->logger->debug(var_export($result, true), ['pre' => true]);
                    $routes[] = $result;
                }
            }
        }

        return $routes;
    }

    private function validRoute($fields): bool
    {
        $this->logger->notice(__METHOD__);
        $headers = [
            'Accept'           => 'application/json, text/javascript, */*; q=0.01',
            'Content-Type'     => 'application/x-www-form-urlencoded; charset=UTF-8',
            'Origin'           => 'https://flyasiana.com',
            'Referer'          => 'https://flyasiana.com/C/KR/EN/index',
            'X-Requested-With' => 'XMLHttpRequest',
        ];

//        $dataFrom = \Cache::getInstance()->get('ra_asiana_origins');
        $dataFrom = null;

        if (!$dataFrom || !is_array($dataFrom)) {
            $postData = [
                'seg'        => 'dep1',
                'bizType'    => 'RED',
                'depArrType' => 'DEP',
                'depAirport' => '',
                'depArea'    => '',
                'tripType'   => 'OW',
                'domIntType' => '',
            ];
            $this->http->RetryCount = 0;
            $this->http->PostURL("https://flyasiana.com/I/KR/EN/AreaAirportInfo.do?n\$eum=132119709619612770", $postData,
                $headers);

            if (strpos($this->http->currentUrl(), 'pc/noticeSystemMaintenance.html') !== false) {
                $msg = $this->http->FindSingleNode("//p[contains(.,'Note of a regular system maintenance on Sunday')]");

                if ($msg && $this->attempt > 1) {
                    throw new \CheckException($msg, ACCOUNT_PROVIDER_ERROR);
                }

                throw new \CheckRetryNeededException(5, 7);
            }

            $dataFrom = $this->http->JsonLog(null, 1, true);

            if (!$dataFrom) {
                $this->http->PostURL("https://flyasiana.com/I/KR/EN/AreaAirportInfo.do?n\$eum=104839599155119220", $postData,
                    $headers);
                $dataFrom = $this->http->JsonLog(null, 1, true);
            }
            $this->http->RetryCount = 2;

            // retries
            if ($this->isBadProxy() || $this->http->Response['code'] == 500) {
                if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                    $this->logger->debug("[attempt]: {$this->attempt}");

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            if (!empty($dataFrom) && isset($dataFrom['RouteCityAirportData'])) {
                \Cache::getInstance()->set('ra_asiana_origins', $dataFrom, 60 * 60 * 24);
            }
            $dataFrom = \Cache::getInstance()->get('ra_asiana_origins');

            if (!isset($dataFrom) || !is_array($dataFrom)) {
                if ($this->http->Response['code'] !== 500) {
                    $this->sendNotification("check origins // ZM");
                }

                throw new \CheckRetryNeededException(5, 0);
            }
        }

        if (isset($dataFrom) && is_array($dataFrom['RouteCityAirportData'])) {
            $inOrigins = false;

            foreach ($dataFrom['RouteCityAirportData'] as $routeCityAirportDatum) {
                foreach ($routeCityAirportDatum['cityAirportDatas'] as $origin) {
                    $this->logger->debug($origin['airport']);

                    if ($origin['airport'] === $fields['DepCode']) {
                        $this->depData = $origin;
                        $this->logger->debug(var_export($this->depData, true));
                        $inOrigins = true;

                        break 2;
                    }
                }
            }

            if (!$inOrigins) {
                $this->SetWarning($fields['DepCode'] . " is not in list of origins");

                return false;
            }

            $dataTo = null;

            if (!$dataTo || !is_array($dataTo)) {
                $postData = [
                    'seg'        => 'arr1',
                    'bizType'    => 'RED',
                    'depArrType' => 'ARR',
                    'depAirport' => $fields['DepCode'],
                    'depArea'    => '',
                    'tripType'   => 'OW',
                    'domIntType' => '',
                ];
                $this->http->RetryCount = 0;
                $this->http->PostURL("https://flyasiana.com/I/KR/EN/AreaAirportInfo.do?n\$eum=60804209401414350",
                    $postData, $headers);
                $dataTo = $this->http->JsonLog(null, 1, true);

                if (!$dataTo) {
                    $this->http->PostURL("https://flyasiana.com/I/KR/EN/AreaAirportInfo.do?n\$eum=11063851122421868",
                        $postData, $headers);
                    $dataTo = $this->http->JsonLog(null, 1, true);
                }
                $this->http->RetryCount = 2;

                // retries
                if ($this->isBadProxy()) {
                    if (ConfigValue(CONFIG_SITE_STATE) != SITE_STATE_DEBUG) {
                        $this->logger->debug("[attempt]: {$this->attempt}");

                        throw new \CheckRetryNeededException(5, 0);
                    }
                }

                if (!empty($dataTo) && isset($dataTo['RouteCityAirportData'])) {
                    \Cache::getInstance()->set('ra_asiana_destinations_' . $fields['DepCode'], $dataTo, 60 * 60 * 24);
                } else {
                    if ($this->http->Response['code'] !== 500) {
                        $this->sendNotification("check destinations // ZM");
                    }

                    throw new \CheckRetryNeededException(5, 0);
                }
            }

            if (is_array($dataTo['RouteCityAirportData'])) {
                $inDestinations = false;

                foreach ($dataTo['RouteCityAirportData'] as $routeCityAirportDatum) {
                    foreach ($routeCityAirportDatum['cityAirportDatas'] as $destination) {
                        $this->logger->debug($destination['airport']);

                        if ($destination['airport'] === $fields['ArrCode']) {
                            $this->arrData = $destination;
                            $this->logger->debug(var_export($this->arrData, true));
                            $inDestinations = true;

                            break 2;
                        }
                    }
                }

                if (!$inDestinations) {
                    $this->SetWarning($fields['ArrCode'] . " is not in list of destinations");

                    return false;
                }
            }
        }

        return true;
    }

    private function generateUUID()
    {
        $script = /** @lang JavaScript */
            "    
		    var d = new Date().getTime(),
			uuid = 'xxxxxxxx-xxxx-4xxx-yxxx-xxxxxxxxxxxx'.replace(/[xy]/g, function(c) {
				var r = (d + Math.random()*16)%16 | 0;
				d = Math.floor(d/16);
				return (c=='x' ? r : (r&0x7|0x8)).toString(16);
			});    
            sendResponseToPhp(uuid);
        ";
        $jsExecutor = $this->services->get(\AwardWallet\Common\Parsing\JsExecutor::class);
        $uuid = $jsExecutor->executeString($script);

        return $uuid;
    }

    private function sensorSensorData()
    {
        $this->logger->notice(__METHOD__);
        $form = $this->http->Form;
        $formURL = $this->http->FormURL;
        $referer = $this->http->currentUrl();

        $sensorPostUrl = $this->http->FindPreg("#_cf\.push\(\['_setAu',\s*'(/.+?)'\]\);#");

        if (!$sensorPostUrl) {
            $sensorPostUrl = $this->http->FindPreg('/src="(\/[^"]+)"><\/script><\/body>/');
        }

        if (!$sensorPostUrl) {
            $sensorPostUrl = $this->http->FindPreg('/src="(\/[^"]+)"><\/script><\/deepl-input-controller>/');
        }

        if (!$sensorPostUrl) {
            $this->logger->error("sensor_data URL not found");

            return null;
        }
        $this->http->NormalizeURL($sensorPostUrl);

        $this->http->Form = $form;
        $this->http->FormURL = $formURL;
        $this->http->setDefaultHeader("Referer", $referer);

        $this->http->setCookie("_abck", $this->abck[$this->index]);

        if (count($this->sensorData) != count($this->secondSensorData)) {
            $this->logger->error("wrong sensor data values");

            return null;
        }

        $this->logger->notice("key: {$this->index}");

        sleep(1);
        $this->http->RetryCount = 0;
        $this->http->setUserAgent($this->userAgents[$this->index]);
        $sensorDataHeaders = [
            "Accept"       => "*/*",
            "Content-type" => "text/plain;charset=UTF-8",
            "Origin"       => "https://flyasiana.com",
            "Referer"      => $this->http->currentUrl(),
        ];
        $sensorData = [
            'sensor_data' => $this->sensorData[$this->index],
        ];
        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        sleep(1);
        $sensorData = [
            'sensor_data' => $this->secondSensorData[$this->index],
        ];

        $this->http->PostURL($sensorPostUrl, json_encode($sensorData), $sensorDataHeaders);
        $this->http->JsonLog();
        $this->http->RetryCount = 2;
        sleep(1);

        return $this->index;
    }
}
