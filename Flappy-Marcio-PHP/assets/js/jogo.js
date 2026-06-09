const canvas = document.getElementById('jogo');
const ctx = canvas.getContext('2d');
const painelJogo = document.getElementById('painelJogo');
const formLoginJogador = document.getElementById('formLoginJogador');
const formCadastroJogador = document.getElementById('formCadastroJogador');
const usuarioLogin = document.getElementById('usuarioLogin');
const senhaLogin = document.getElementById('senhaLogin');
const usuarioCadastro = document.getElementById('usuarioCadastro');
const senhaCadastro = document.getElementById('senhaCadastro');
const btnSairJogador = document.getElementById('btnSairJogador');
const abasAcesso = document.getElementById('abasAcesso');
const mensagemNome = document.getElementById('mensagemNome');
const nomeAtual = document.getElementById('nomeAtual');
const statusJogador = document.getElementById('statusJogador');
const recordeJogador = document.getElementById('recordeJogador');
const posicaoJogador = document.getElementById('posicaoJogador');
const faseAtual = document.getElementById('faseAtual');
const velocidadeHud = document.getElementById('velocidadeHud');
const hudDificuldade = document.getElementById('hudDificuldade');
const dificuldadeAtual = document.getElementById('dificuldadeAtual');
const perfilPartidas = document.getElementById('perfilPartidas');
const perfilFase = document.getElementById('perfilFase');
const perfilMedia = document.getElementById('perfilMedia');
const perfilConquistas = document.getElementById('perfilConquistas');
const btnTelaCheia = document.getElementById('btnTelaCheia');
const btnApresentacao = document.getElementById('btnApresentacao');
const skinAtual = document.getElementById('skinAtual');
const nivelJogador = document.getElementById('nivelJogador');
const tituloNivel = document.getElementById('tituloNivel');
const barraXp = document.getElementById('barraXp');
const xpTexto = document.getElementById('xpTexto');
const btnAtualizarRanking = document.getElementById('btnAtualizarRanking');
const btnManual = document.getElementById('btnManual');
const manualCard = document.getElementById('manualCard');
const manualIcone = document.getElementById('manualIcone');
const dicaFlutuante = document.querySelector('.dica-flutuante');
const conquistasJogador = document.getElementById('conquistasJogador');
const resumoFinal = document.getElementById('resumoFinal');

const config = Object.assign({
    velocidade_inicial: 3.85,
    aumento_fase: 0.34,
    velocidade_maxima: 6.4,
    pontos_por_fase: 6,
    abertura_inicial: 218,
    abertura_minima: 178,
    gravidade: 0.46,
    pulo: -8.25
}, window.CONFIG_JOGO || {});

const dificuldades = window.DIFICULDADES_JOGO || {
    facil: { nome: 'Fácil', vel: 0.88, abertura: 28, max: -0.35 },
    normal: { nome: 'Normal', vel: 1, abertura: 0, max: 0 },
    dificil: { nome: 'Difícil', vel: 1.12, abertura: -18, max: 0.35 },
    insano: { nome: 'Insano', vel: 1.23, abertura: -30, max: 0.65 }
};


const skins = window.SKINS_JOGO || {
    classica: { nome: 'Clássica', pontos: 0, fase: 1, partidas: 0 },
    azul: { nome: 'Azul', pontos: 8, fase: 1, partidas: 0 },
    dourada: { nome: 'Dourada', pontos: 0, fase: 4, partidas: 0 },
    neon: { nome: 'Neon', pontos: 0, fase: 1, partidas: 8 }
};

const filtrosSkin = {
    classica: 'none',
    azul: 'hue-rotate(155deg) saturate(1.25)',
    dourada: 'sepia(0.65) saturate(1.7) hue-rotate(8deg) brightness(1.08)',
    neon: 'hue-rotate(285deg) saturate(1.8) brightness(1.18)'
};

const imagens = {
    fundo: carregarImagem('assets/imgs/bg.png'),
    chao: carregarImagem('assets/imgs/base.png'),
    cano: carregarImagem('assets/imgs/pipe.png'),
    menu: carregarImagem('assets/imgs/menu.png'),
    passaro: [carregarImagem('assets/imgs/1.png'), carregarImagem('assets/imgs/2.png'), carregarImagem('assets/imgs/3.png')],
    marcio: Array.from({ length: 30 }, (_, i) => carregarImagem(`marcioimg/frame-${String(i + 1).padStart(2, '0')}.gif`))
};

const largura = canvas.width;
const altura = canvas.height;
const chaoAltura = 90;
const canoLargura = 70;
let estado = 'inicio';
let pontos = 0;
let quadro = 0;
let chaoX = 0;
let inicioPartida = 0;
let canos = [];
let particulas = [];
let popups = [];
let powerups = [];
let brilhos = [];
let escudoAtivo = false;
let focoAtivo = 0;
let ultimoPowerup = 0;
let salvouPontuacao = false;
let jogador = '';
let jogadorLogado = false;
let dificuldade = localStorage.getItem('flappy_marcio_dificuldade') || 'normal';
let skinSelecionada = localStorage.getItem('flappy_marcio_skin') || 'classica';
let perfilAtual = null;
let mensagemFinal = '';
let avisoJogo = 'Pressione espaço para começar';
let faseAnterior = 1;
let shake = 0;
let filtroRanking = 'geral';
let passaro = criarPassaro();

function carregarImagem(caminho) {
    const img = new Image();
    img.src = caminho;
    return img;
}

function criarPassaro() {
    return { x: 105, y: 325, largura: 58, altura: 58, velocidade: 0, angulo: 0, frame: 0 };
}

function ajustarNome(nome) {
    return nome.trim().replace(/\s+/g, ' ').slice(0, 24);
}

function nomeValido(nome) {
    return ajustarNome(nome).length >= 2;
}

function modoAtual() {
    return dificuldades[dificuldade] || dificuldades.normal;
}

function skinDados() {
    return skins[skinSelecionada] || skins.classica;
}

function skinLiberada(skin, perfil) {
    const dados = skins[skin] || skins.classica;
    if (skin === 'classica') return true;
    if (!perfil) return false;
    return Number(perfil.melhor || 0) >= Number(dados.pontos || 0)
        && Number(perfil.fase || 1) >= Number(dados.fase || 1)
        && Number(perfil.partidas || 0) >= Number(dados.partidas || 0);
}

function atualizarSkins(perfil = perfilAtual) {
    perfilAtual = perfil;
    if (!skinLiberada(skinSelecionada, perfilAtual)) skinSelecionada = 'classica';
    localStorage.setItem('flappy_marcio_skin', skinSelecionada);
    if (skinAtual) skinAtual.textContent = skinDados().nome || 'Clássica';
    document.querySelectorAll('.skin-opcao').forEach(botao => {
        const chave = botao.dataset.skin || 'classica';
        const liberada = skinLiberada(chave, perfilAtual);
        botao.classList.toggle('ativa', chave === skinSelecionada);
        botao.classList.toggle('bloqueada', !liberada);
        botao.title = liberada ? 'Selecionar skin' : 'Bloqueada';
    });
}


function faseDoJogo() {
    return Math.max(1, Math.floor(pontos / Number(config.pontos_por_fase || 6)) + 1);
}

function velocidadeAtual() {
    const modo = modoAtual();
    const base = Number(config.velocidade_inicial || 3.85) * Number(modo.vel || 1);
    const aumento = Number(config.aumento_fase || 0.34);
    const maximo = Number(config.velocidade_maxima || 6.4) + Number(modo.max || 0);
    const calculada = Math.min(maximo, base + (faseDoJogo() - 1) * aumento);
    return focoAtivo > 0 ? Math.max(base, calculada - 0.55) : calculada;
}

function multiplicadorVelocidade() {
    return velocidadeAtual() / Number(config.velocidade_inicial || 3.85);
}

function aberturaAtual() {
    const modo = modoAtual();
    const inicial = Number(config.abertura_inicial || 218) + Number(modo.abertura || 0);
    const minima = Number(config.abertura_minima || 178) + Number(modo.abertura || 0) * 0.5;
    return Math.max(minima, inicial - (faseDoJogo() - 1) * 5.5);
}

function intervaloCanos() {
    return Math.max(70, 98 - (faseDoJogo() - 1) * 3);
}

function amplitudeCano() {
    if (faseDoJogo() >= 7) return 16;
    if (faseDoJogo() >= 5) return 11;
    if (faseDoJogo() >= 4) return 6;
    return 0;
}

function progressoFase() {
    const passo = Number(config.pontos_por_fase || 6);
    return (pontos % passo) / passo;
}

function proximaFase() {
    return faseDoJogo() * Number(config.pontos_por_fase || 6);
}

function mostrarMensagem(texto, ok = false) {
    mensagemNome.textContent = texto;
    mensagemNome.classList.toggle('ok', ok);
}

function atualizarDificuldadeTela() {
    document.querySelectorAll('.dificuldade-opcao').forEach(botao => {
        botao.classList.toggle('ativa', botao.dataset.dificuldade === dificuldade);
    });
    const nome = modoAtual().nome || 'Normal';
    dificuldadeAtual.textContent = nome;
    hudDificuldade.textContent = nome;
}

function atualizarPlacarTela() {
    faseAtual.textContent = faseDoJogo();
    velocidadeHud.textContent = `${multiplicadorVelocidade().toFixed(1)}x`;

    if (estado === 'jogando' && faseDoJogo() > faseAnterior) {
        faseAnterior = faseDoJogo();
        avisoJogo = `Fase ${faseAnterior}: mais velocidade`;
        popups.push({ texto: `Fase ${faseAnterior}!`, x: largura / 2, y: 190, vida: 92, cor: '#ffe66d', tamanho: 34 });
        criarParticulas(30, '#ffe66d', passaro.x + 35, passaro.y + 30);
    }
}

function definirJogador(dados) {
    if (!dados || !dados.jogador) {
        jogador = '';
        jogadorLogado = false;
        nomeAtual.textContent = 'Visitante';
        statusJogador.textContent = 'Visitante';
        if (btnSairJogador) btnSairJogador.classList.add('escondido');
        if (abasAcesso) abasAcesso.classList.remove('conta-logada');
        preencherPerfil(null);
        atualizarResumoJogador(0, null, [], null);
        atualizarDicaFlutuante();
        return;
    }

    jogador = ajustarNome(dados.jogador.nome || '');
    jogadorLogado = true;
    nomeAtual.textContent = jogador || 'Jogador';
    statusJogador.textContent = 'Logado';
    if (usuarioLogin) usuarioLogin.value = jogador;
    if (btnSairJogador) btnSairJogador.classList.remove('escondido');
    if (abasAcesso) abasAcesso.classList.add('conta-logada');
    mostrarMensagem('Conta ativa. Pressione espaço para começar.', true);
    atualizarResumoJogador(dados.recorde || (dados.perfil ? dados.perfil.melhor : 0), dados.posicao || null, dados.conquistas || [], dados.perfil || null);
    if (dados.jogador.skin && skins[dados.jogador.skin]) {
        skinSelecionada = dados.jogador.skin;
        localStorage.setItem('flappy_marcio_skin', skinSelecionada);
        atualizarSkins(dados.perfil || null);
    }
    atualizarDicaFlutuante();
}

async function enviarAcesso(acao, usuario, senha) {
    const dados = new FormData();
    dados.append('acao', acao);
    dados.append('usuario', ajustarNome(usuario).toLowerCase());
    dados.append('senha', senha);
    const resposta = await fetch('php/ranking.php', { method: 'POST', body: dados });
    const retorno = await resposta.json();
    if (!retorno.ok) throw new Error(retorno.mensagem || 'Não foi possível acessar.');
    definirJogador(retorno);
    carregarRanking();
}

async function carregarSessao() {
    try {
        const resposta = await fetch('php/ranking.php?acao=sessao');
        const retorno = await resposta.json();
        if (retorno.ok && retorno.logado) definirJogador(retorno);
        else definirJogador(null);
    } catch (erro) {
        definirJogador(null);
    }
}

function tentarComecar() {
    if (!jogadorLogado) {
        mostrarMensagem('Entre ou crie uma conta para jogar e salvar seu progresso.');
        if (usuarioLogin) usuarioLogin.focus();
        return;
    }
    reiniciar();
}

function reiniciar() {
    estado = 'jogando';
    pontos = 0;
    quadro = 0;
    chaoX = 0;
    inicioPartida = performance.now();
    canos = [];
    particulas = [];
    popups = [];
    powerups = [];
    brilhos = [];
    escudoAtivo = false;
    focoAtivo = 0;
    ultimoPowerup = 0;
    salvouPontuacao = false;
    mensagemFinal = '';
    avisoJogo = 'Valendo!';
    faseAnterior = 1;
    shake = 0;
    passaro = criarPassaro();
    adicionarCano();
    atualizarPlacarTela();
    atualizarDicaFlutuante();
}

function adicionarCano() {
    const abertura = aberturaAtual();
    const minimo = 72;
    const maximo = altura - chaoAltura - abertura - 82;
    const topo = Math.floor(Math.random() * (maximo - minimo + 1)) + minimo;
    canos.push({ x: largura + 40, topo, base: topo + abertura, passou: false, seed: Math.random() * 220, move: faseDoJogo() >= 4 });
}

function deslocamentoCano(cano) {
    if (!cano.move) return 0;
    return Math.sin((quadro + cano.seed) / 40) * amplitudeCano();
}

function pular() {
    if (estado !== 'jogando') return;
    passaro.velocidade = Number(config.pulo || -8.25);
    criarParticulas(7, '#fff6a7', passaro.x + 8, passaro.y + passaro.altura / 2);
}

function alternarPausa() {
    if (estado === 'jogando') {
        estado = 'pausado';
        avisoJogo = 'Pausado';
    } else if (estado === 'pausado') {
        estado = 'jogando';
        avisoJogo = 'Valendo!';
    }
    atualizarDicaFlutuante();
}

function encerrarJogo() {
    if (estado === 'fim') return;
    estado = 'fim';
    shake = 14;
    atualizarPlacarTela();
    atualizarDicaFlutuante();
    salvarPontuacao();
}

function criarParticulas(qtd, cor, x = passaro.x, y = passaro.y) {
    for (let i = 0; i < qtd; i++) {
        particulas.push({ x, y: y + (Math.random() * 24 - 12), vx: -1.2 - Math.random() * 2.8, vy: Math.random() * 2.4 - 1.2, r: 2 + Math.random() * 3.3, vida: 28 + Math.random() * 22, cor });
    }
}

function criarBrilho() {
    if (estado !== 'jogando' || Math.random() > 0.025) return;
    brilhos.push({ x: largura + 20, y: 50 + Math.random() * 490, r: 1.5 + Math.random() * 2.5, vida: 210 });
}

function criarPowerup() {
    if (estado !== 'jogando') return;
    if (pontos < 3 || pontos - ultimoPowerup < 4) return;
    if (Math.random() > 0.28) return;
    const tipos = escudoAtivo ? ['foco'] : ['escudo', 'foco'];
    const tipo = tipos[Math.floor(Math.random() * tipos.length)];
    ultimoPowerup = pontos;
    powerups.push({ tipo, x: largura + 70, y: 120 + Math.random() * (altura - chaoAltura - 260), r: tipo === 'escudo' ? 17 : 15, angulo: 0, pego: false });
}

function aplicarPowerup(item) {
    item.pego = true;
    if (item.tipo === 'escudo') {
        escudoAtivo = true;
        avisoJogo = 'Escudo ativado!';
        popups.push({ texto: 'Escudo!', x: item.x, y: item.y - 18, vida: 70, cor: '#7dd3fc', tamanho: 24 });
        criarParticulas(24, '#7dd3fc', item.x, item.y);
        return;
    }
    focoAtivo = 260;
    avisoJogo = 'Foco ativado!';
    popups.push({ texto: 'Foco!', x: item.x, y: item.y - 18, vida: 70, cor: '#c4b5fd', tamanho: 24 });
    criarParticulas(24, '#c4b5fd', item.x, item.y);
}

function atualizarPowerups(velocidade) {
    if (focoAtivo > 0 && estado === 'jogando') focoAtivo--;
    criarPowerup();
    powerups.forEach(item => {
        item.x -= velocidade * 0.95;
        item.angulo += 0.045;
        const dx = passaro.x + passaro.largura / 2 - item.x;
        const dy = passaro.y + passaro.altura / 2 - item.y;
        if (!item.pego && estado === 'jogando' && Math.sqrt(dx * dx + dy * dy) < item.r + 28) aplicarPowerup(item);
    });
    powerups = powerups.filter(item => !item.pego && item.x > -40);
}

function usarEscudo() {
    if (!escudoAtivo) return false;
    escudoAtivo = false;
    shake = 7;
    passaro.velocidade = Number(config.pulo || -8.25) * 0.72;
    canos = canos.filter(cano => cano.x + canoLargura < passaro.x - 18 || cano.x > passaro.x + 145);
    avisoJogo = 'Escudo salvou você!';
    popups.push({ texto: 'Defendeu!', x: passaro.x + 84, y: passaro.y, vida: 75, cor: '#7dd3fc', tamanho: 25 });
    criarParticulas(34, '#7dd3fc', passaro.x + 30, passaro.y + 28);
    return true;
}

function atualizarParticulas() {
    particulas.forEach(p => { p.x += p.vx; p.y += p.vy; p.vida--; });
    particulas = particulas.filter(p => p.vida > 0);
    popups.forEach(p => { p.y -= 0.45; p.vida--; });
    popups = popups.filter(p => p.vida > 0);
    criarBrilho();
    brilhos.forEach(e => { e.x -= velocidadeAtual() * 0.28; e.vida--; });
    brilhos = brilhos.filter(e => e.vida > 0 && e.x > -20);
}

function atualizar() {
    quadro++;
    const velocidade = velocidadeAtual();
    atualizarPowerups(velocidade);
    chaoX = (chaoX - velocidade) % 336;
    atualizarParticulas();
    if (shake > 0) shake--;
    if (estado !== 'jogando') return;

    passaro.velocidade += Number(config.gravidade || 0.46);
    passaro.y += passaro.velocidade;
    passaro.angulo = passaro.velocidade < 0 ? -22 : Math.min(80, passaro.angulo + 3.2);
    passaro.frame = Math.floor(quadro / 7) % imagens.passaro.length;

    if (quadro % 3 === 0) criarParticulas(1, faseDoJogo() >= 4 ? '#b7f7ff' : '#ffffff', passaro.x + 6, passaro.y + 30);
    if (quadro % intervaloCanos() === 0) adicionarCano();

    canos.forEach(cano => {
        cano.x -= velocidade;
        if (!cano.passou && cano.x + canoLargura < passaro.x) {
            cano.passou = true;
            pontos++;
            avisoJogo = faseDoJogo() >= 4 ? 'Canos em movimento!' : 'Continue!';
            popups.push({ texto: '+1', x: passaro.x + 84, y: passaro.y + 12, vida: 42, cor: '#ffe66d', tamanho: 22 });
            atualizarPlacarTela();
        }
    });
    canos = canos.filter(cano => cano.x > -canoLargura - 10);

    if (passaro.y < -10 || passaro.y + passaro.altura > altura - chaoAltura) {
        if (!usarEscudo()) encerrarJogo();
        return;
    }
    for (const cano of canos) {
        if (colidiuComCano(cano)) {
            if (!usarEscudo()) encerrarJogo();
            return;
        }
    }
}

function colidiuComCano(cano) {
    const folga = 12;
    const px = passaro.x + folga;
    const py = passaro.y + folga;
    const pw = passaro.largura - folga * 2;
    const ph = passaro.altura - folga * 2;
    const ajuste = deslocamentoCano(cano);
    const topo = cano.topo + ajuste;
    const base = cano.base + ajuste;
    const dentroX = px < cano.x + canoLargura && px + pw > cano.x;
    return dentroX && (py < topo || py + ph > base);
}

function desenhar() {
    ctx.save();
    if (shake > 0) ctx.translate(Math.random() * shake - shake / 2, Math.random() * shake - shake / 2);
    desenharFundo();
    desenharCenarioVivo();
    desenharVelocidade();
    desenharCanos();
    desenharPowerups();
    desenharParticulas();
    desenharPassaro();
    desenharEfeitosJogador();
    desenharChao();
    desenharHudCanvas();
    desenharPopups();
    if (estado === 'inicio') desenharTelaInicial();
    if (estado === 'pausado') desenharPausado();
    if (estado === 'fim') desenharGameOver();
    ctx.restore();
}

function desenharFundo() {
    ctx.drawImage(imagens.fundo, 0, 0, largura, altura);
    const grad = ctx.createLinearGradient(0, 0, 0, altura);
    grad.addColorStop(0, faseDoJogo() >= 5 ? 'rgba(255,118,92,.18)' : 'rgba(255,230,109,.08)');
    grad.addColorStop(1, faseDoJogo() >= 4 ? 'rgba(14,165,233,.16)' : 'rgba(255,255,255,.05)');
    ctx.fillStyle = grad;
    ctx.fillRect(0, 0, largura, altura);
}

function desenharCenarioVivo() {
    ctx.save();
    brilhos.forEach(e => {
        ctx.globalAlpha = Math.min(0.75, e.vida / 90);
        ctx.fillStyle = '#fff7ad';
        ctx.beginPath();
        ctx.arc(e.x, e.y, e.r, 0, Math.PI * 2);
        ctx.fill();
    });
    ctx.restore();
}

function desenharVelocidade() {
    if (estado !== 'jogando' || faseDoJogo() < 3) return;
    ctx.save();
    ctx.strokeStyle = 'rgba(255, 255, 255, .25)';
    ctx.lineWidth = 2;
    for (let i = 0; i < 9; i++) {
        const y = 60 + i * 74 + ((quadro * velocidadeAtual()) % 74);
        ctx.beginPath();
        ctx.moveTo(largura + 20, y);
        ctx.lineTo(largura - 90 - i * 12, y + 18);
        ctx.stroke();
    }
    ctx.restore();
}

function desenharCanos() {
    canos.forEach(cano => {
        const ajuste = deslocamentoCano(cano);
        const topo = cano.topo + ajuste;
        const base = cano.base + ajuste;
        ctx.save();
        ctx.translate(cano.x + canoLargura / 2, topo);
        ctx.scale(1, -1);
        ctx.drawImage(imagens.cano, -canoLargura / 2, 0, canoLargura, 430);
        ctx.restore();
        ctx.drawImage(imagens.cano, cano.x, base, canoLargura, 430);
    });
}

function desenharPowerups() {
    ctx.save();
    powerups.forEach(item => {
        ctx.save();
        ctx.translate(item.x, item.y);
        ctx.rotate(item.angulo);
        if (item.tipo === 'escudo') {
            const g = ctx.createRadialGradient(0, 0, 3, 0, 0, 25);
            g.addColorStop(0, '#ffffff');
            g.addColorStop(0.35, '#7dd3fc');
            g.addColorStop(1, 'rgba(14,116,144,.18)');
            ctx.fillStyle = g;
            ctx.beginPath();
            ctx.arc(0, 0, 24, 0, Math.PI * 2);
            ctx.fill();
            ctx.strokeStyle = 'rgba(255,255,255,.95)';
            ctx.lineWidth = 3;
            ctx.beginPath();
            ctx.moveTo(0, -14); ctx.lineTo(13, -6); ctx.lineTo(9, 13); ctx.lineTo(0, 19); ctx.lineTo(-9, 13); ctx.lineTo(-13, -6); ctx.closePath(); ctx.stroke();
        } else {
            ctx.strokeStyle = '#ddd6fe';
            ctx.lineWidth = 6;
            ctx.beginPath();
            ctx.arc(0, 0, 18, 0, Math.PI * 2);
            ctx.stroke();
            ctx.strokeStyle = 'rgba(255,255,255,.9)';
            ctx.lineWidth = 2;
            ctx.beginPath();
            ctx.arc(0, 0, 9, 0, Math.PI * 2);
            ctx.stroke();
        }
        ctx.restore();
    });
    ctx.restore();
}

function desenharParticulas() {
    ctx.save();
    particulas.forEach(p => {
        ctx.globalAlpha = Math.max(0, p.vida / 48);
        ctx.fillStyle = p.cor;
        ctx.beginPath();
        ctx.arc(p.x, p.y, p.r, 0, Math.PI * 2);
        ctx.fill();
    });
    ctx.restore();
}

function desenharPassaro() {
    const img = imagens.passaro[passaro.frame];
    ctx.save();
    ctx.translate(passaro.x + passaro.largura / 2, passaro.y + passaro.altura / 2);
    ctx.rotate(passaro.angulo * Math.PI / 180);
    if (skinSelecionada === 'dourada' || skinSelecionada === 'neon') {
        ctx.globalAlpha = skinSelecionada === 'neon' ? 0.44 : 0.28;
        ctx.fillStyle = skinSelecionada === 'neon' ? '#f0abfc' : '#fde68a';
        ctx.beginPath();
        ctx.arc(0, 0, 42 + Math.sin(quadro / 7) * 3, 0, Math.PI * 2);
        ctx.fill();
        ctx.globalAlpha = 1;
    }
    ctx.filter = filtrosSkin[skinSelecionada] || 'none';
    ctx.drawImage(img, -passaro.largura / 2, -passaro.altura / 2, passaro.largura, passaro.altura);
    ctx.filter = 'none';
    ctx.restore();
}

function desenharEfeitosJogador() {
    if (!escudoAtivo) return;
    ctx.save();
    ctx.strokeStyle = 'rgba(125,211,252,.92)';
    ctx.lineWidth = 4;
    ctx.beginPath();
    ctx.arc(passaro.x + passaro.largura / 2, passaro.y + passaro.altura / 2, 40 + Math.sin(quadro / 6) * 3, 0, Math.PI * 2);
    ctx.stroke();
    ctx.restore();
}

function desenharChao() {
    const y = altura - chaoAltura;
    for (let x = chaoX - 336; x < largura + 336; x += 336) ctx.drawImage(imagens.chao, x, y, 336, chaoAltura);
}

function textoComSombra(texto, x, y, tamanho = 28, alinhamento = 'left', cor = '#fff') {
    ctx.save();
    ctx.font = `bold ${tamanho}px Arial`;
    ctx.textAlign = alinhamento;
    ctx.lineWidth = 5;
    ctx.strokeStyle = 'rgba(0,0,0,.45)';
    ctx.fillStyle = cor;
    ctx.strokeText(texto, x, y);
    ctx.fillText(texto, x, y);
    ctx.restore();
}

function desenharBarraFase() {
    const x = 24, y = 86, w = 176, h = 12;
    ctx.save();
    ctx.fillStyle = 'rgba(0,0,0,.25)';
    ctx.fillRect(x, y, w, h);
    ctx.fillStyle = '#ffe66d';
    ctx.fillRect(x, y, w * progressoFase(), h);
    ctx.strokeStyle = 'rgba(255,255,255,.8)';
    ctx.strokeRect(x, y, w, h);
    ctx.font = 'bold 13px Arial';
    ctx.fillStyle = '#fff';
    ctx.fillText(`Próxima fase: ${proximaFase()}`, x, y + 29);
    ctx.restore();
}

function desenharHudCanvas() {
    textoComSombra(`${pontos} pts`, largura - 24, 48, 36, 'right');
    textoComSombra(`Fase ${faseDoJogo()}`, 24, 45, 24, 'left');
    desenharBarraFase();
    const extras = [];
    if (escudoAtivo) extras.push('Escudo');
    if (focoAtivo > 0) extras.push('Foco');
    if (extras.length) textoComSombra(extras.join(' + '), largura - 24, 90, 18, 'right', '#7dd3fc');
    if (estado === 'jogando' && avisoJogo) textoComSombra(avisoJogo, largura / 2, 132, 18, 'center', '#ffe66d');
}

function desenharPopups() {
    ctx.save();
    popups.forEach(p => {
        ctx.globalAlpha = Math.min(1, p.vida / 25);
        textoComSombra(p.texto, p.x, p.y, p.tamanho, 'center', p.cor);
    });
    ctx.restore();
}

function caixaCentral(x, y, w, h, cor = 'rgba(3,64,94,.75)') {
    x = Math.round(x);
    y = Math.round(y);
    const r = 24;
    ctx.save();
    ctx.fillStyle = cor;
    ctx.strokeStyle = 'rgba(255,255,255,.66)';
    ctx.lineWidth = 3;
    ctx.beginPath();
    ctx.moveTo(x + r, y); ctx.lineTo(x + w - r, y); ctx.quadraticCurveTo(x + w, y, x + w, y + r); ctx.lineTo(x + w, y + h - r); ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h); ctx.lineTo(x + r, y + h); ctx.quadraticCurveTo(x, y + h, x, y + h - r); ctx.lineTo(x, y + r); ctx.quadraticCurveTo(x, y, x + r, y); ctx.fill(); ctx.stroke();
    ctx.restore();
}


function caixaCentralizada(y, w, h, cor = 'rgba(3,64,94,.75)') {
    caixaCentral((largura - w) / 2, y, w, h, cor);
}

function desenharTelaInicial() {
    ctx.save();
    ctx.drawImage(imagens.menu, 0, 0, largura, altura);
    ctx.fillStyle = 'rgba(4,40,66,.48)';
    ctx.fillRect(0, 0, largura, altura);
    caixaCentralizada(210, 500, 314);
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.font = 'bold 48px Arial';
    ctx.fillText('Flappy Márcio', largura / 2, 285);
    ctx.font = '22px Arial';
    ctx.fillText('Pressione espaço para voar', largura / 2, 338);
    ctx.font = '18px Arial';
    ctx.fillText(`${modoAtual().nome} • ${skinDados().nome} • missões`, largura / 2, 380);
    ctx.fillText('escudo, foco e fases progressivas', largura / 2, 410);
    ctx.font = 'bold 20px Arial';
    ctx.fillStyle = '#ffe66d';
    ctx.fillText('Espaço inicia • P pausa', largura / 2, 464);
    ctx.restore();
}

function desenharPausado() {
    ctx.save();
    ctx.fillStyle = 'rgba(4,40,66,.56)';
    ctx.fillRect(0, 0, largura, altura);
    caixaCentralizada(315, 430, 170);
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.font = 'bold 42px Arial';
    ctx.fillText('Pausado', largura / 2, 386);
    ctx.font = '18px Arial';
    ctx.fillText('Pressione P para continuar', largura / 2, 430);
    ctx.restore();
}

function desenharGameOver() {
    const frame = imagens.marcio[Math.floor(quadro / 5) % imagens.marcio.length];
    ctx.save();
    ctx.fillStyle = 'rgba(4,40,66,.72)';
    ctx.fillRect(0, 0, largura, altura);
    ctx.drawImage(frame, largura / 2 - 88, 116, 176, 176);
    caixaCentralizada(330, 500, 306);
    ctx.fillStyle = '#fff';
    ctx.textAlign = 'center';
    ctx.font = 'bold 40px Arial';
    ctx.fillText('Fim de jogo', largura / 2, 390);
    ctx.font = '21px Arial';
    ctx.fillText(`Pontuação: ${pontos}`, largura / 2, 438);
    ctx.fillText(`Fase: ${faseDoJogo()} • ${modoAtual().nome}`, largura / 2, 474);
    ctx.fillText(`Velocidade: ${multiplicadorVelocidade().toFixed(1)}x`, largura / 2, 510);
    if (mensagemFinal) {
        ctx.font = 'bold 19px Arial';
        ctx.fillStyle = '#ffe66d';
        ctx.fillText(mensagemFinal, largura / 2, 552);
    }
    ctx.fillStyle = '#fff';
    ctx.font = '16px Arial';
    ctx.fillText('Pressione espaço para jogar novamente', largura / 2, 604);
    ctx.restore();
}

function loop() {
    atualizar();
    desenhar();
    requestAnimationFrame(loop);
}

function formatarData(dataTexto) {
    if (!dataTexto) return 'Pontuação registrada';
    const data = new Date(dataTexto.replace(' ', 'T'));
    if (Number.isNaN(data.getTime())) return 'Pontuação registrada';
    return data.toLocaleDateString('pt-BR') + ' ' + data.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' });
}

function medalha(indice) {
    if (indice === 0) return '🥇';
    if (indice === 1) return '🥈';
    if (indice === 2) return '🥉';
    return indice + 1;
}

function escaparTexto(texto) {
    return String(texto).replace(/[&<>'"]/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', "'": '&#039;', '"': '&quot;' }[c]));
}

async function salvarPontuacao() {
    if (salvouPontuacao || pontos <= 0 || !jogadorLogado) {
        if (pontos > 0 && !jogadorLogado) mostrarMensagem('Entre na conta para salvar essa pontuação.');
        atualizarResumoLocal();
        return;
    }

    salvouPontuacao = true;
    const duracao = (performance.now() - inicioPartida) / 1000;
    const dados = new FormData();
    dados.append('acao', 'salvar');
    dados.append('pontos', pontos);
    dados.append('fase', faseDoJogo());
    dados.append('duracao', duracao.toFixed(2));
    dados.append('velocidade', multiplicadorVelocidade().toFixed(2));
    dados.append('dificuldade', dificuldade);
    dados.append('skin', skinSelecionada);

    try {
        const resposta = await fetch('php/ranking.php', { method: 'POST', body: dados });
        const retorno = await resposta.json();
        if (!retorno.ok) throw new Error(retorno.mensagem || 'Falha ao salvar');
        atualizarResumoJogador(retorno.recorde, retorno.posicao, retorno.conquistas || [], retorno.perfil);
        const destaques = [];
        if (retorno.melhorou) destaques.push('Novo recorde pessoal');
        if (retorno.podio) destaques.push('Entrou no pódio');
        (retorno.conquistas || []).forEach(c => destaques.push(c.titulo));
        (retorno.missoesConcluidas || []).forEach(m => destaques.push(`Missão: ${m.titulo}`));
        mensagemFinal = destaques[0] || 'Partida registrada';
        atualizarResumoLocal(retorno, duracao);
        atualizarEstatisticas(retorno.estatisticas);
        carregarRanking();
    } catch (erro) {
        mostrarMensagem('Não foi possível salvar no ranking.');
        atualizarResumoLocal(null, duracao);
    }
}

function atualizarResumoLocal(retorno = null, duracao = null) {
    const tempo = duracao !== null ? duracao : (inicioPartida ? (performance.now() - inicioPartida) / 1000 : 0);
    const conquistas = retorno ? (retorno.conquistas || []) : [];
    const missoes = retorno ? (retorno.missoesConcluidas || []) : [];
    const blocos = [
        ['Pontuação', `${pontos} pontos`],
        ['Fase alcançada', `Fase ${faseDoJogo()}`],
        ['Tempo de jogo', `${tempo.toFixed(1)}s`],
        ['Velocidade final', `${multiplicadorVelocidade().toFixed(1)}x`],
        ['Dificuldade', modoAtual().nome],
        ['Skin usada', skinDados().nome]
    ];
    resumoFinal.innerHTML = blocos.map(item => `<div><strong>${item[0]}</strong><span>${item[1]}</span></div>`).join('');
    const extras = [];
    conquistas.forEach(c => extras.push(c.titulo));
    missoes.forEach(m => extras.push(`Missão: ${m.titulo}`));
    conquistasJogador.innerHTML = extras.length ? extras.map(t => `<span>${escaparTexto(t)}</span>`).join('') : '<span>Continue jogando para liberar conquistas.</span>';
}

async function carregarRanking() {
    const textoOriginal = btnAtualizarRanking.textContent;
    btnAtualizarRanking.disabled = true;
    btnAtualizarRanking.textContent = 'Atualizando...';
    try {
        let periodo = filtroRanking === 'geral' ? 'geral' : filtroRanking;
        let dificuldadeFiltro = 'geral';
        if (String(filtroRanking).startsWith('dif:')) {
            dificuldadeFiltro = filtroRanking.split(':')[1] || 'geral';
            periodo = 'geral';
        }
        const resposta = await fetch(`php/ranking.php?acao=listar&periodo=${periodo}&dificuldade=${dificuldadeFiltro}`);
        const retorno = await resposta.json();
        const lista = document.getElementById('ranking');
        lista.innerHTML = '';
        if (!retorno.ok) throw new Error(retorno.mensagem || 'Falha');
        if (!retorno.ranking.length) {
            lista.innerHTML = '<li class="vazio">Nenhuma pontuação salva ainda.</li>';
            return;
        }
        retorno.ranking.forEach((item, indice) => {
            const li = document.createElement('li');
            li.className = indice < 3 ? `top${indice + 1}` : '';
            li.innerHTML = `<span class="posicao-ranking">${medalha(indice)}</span><span class="nome-ranking"><strong>${escaparTexto(item.nome)}</strong><small>${item.dificuldade} • Fase ${item.fase} • ${formatarData(item.data)}</small></span><span class="pontos-ranking">${item.pontos} pts</span>`;
            lista.appendChild(li);
        });
    } catch (erro) {
        document.getElementById('ranking').innerHTML = '<li class="vazio">Ative o MySQL e abra o setup.php.</li>';
    } finally {
        btnAtualizarRanking.disabled = false;
        btnAtualizarRanking.textContent = textoOriginal;
    }
}

function preencherPerfil(perfil) {
    perfilAtual = perfil;
    perfilPartidas.textContent = perfil ? perfil.partidas : 0;
    perfilFase.textContent = perfil ? perfil.fase : 1;
    perfilMedia.textContent = perfil ? perfil.media : 0;
    perfilConquistas.textContent = perfil ? perfil.conquistas : 0;
    const nivel = perfil ? Number(perfil.nivel || 1) : 1;
    const atual = perfil ? Number(perfil.xpAtual || 0) : 0;
    const proximo = perfil ? Number(perfil.xpProximo || 100) : 100;
    if (nivelJogador) nivelJogador.textContent = `Nível ${nivel}`;
    if (tituloNivel) tituloNivel.textContent = perfil ? perfil.tituloNivel : 'Iniciante';
    if (barraXp) barraXp.style.width = `${Math.min(100, (atual / proximo) * 100)}%`;
    if (xpTexto) xpTexto.textContent = `${atual} / ${proximo} XP`;
    atualizarSkins(perfil);
}

function atualizarResumoJogador(recorde, posicao, conquistas = null, perfil = null) {
    recordeJogador.textContent = Number(recorde || 0);
    posicaoJogador.textContent = posicao ? `${posicao}º` : '--';
    preencherPerfil(perfil);
    atualizarSkins(perfil);
    if (Array.isArray(conquistas) && conquistas.length) {
        conquistasJogador.innerHTML = conquistas.slice(0, 4).map(item => `<span>${escaparTexto(item.titulo)}</span>`).join('');
    }
}

async function carregarDadosJogador() {
    if (!jogadorLogado) {
        atualizarResumoJogador(0, null, [], null);
        return;
    }
    try {
        const resposta = await fetch('php/ranking.php?acao=jogador');
        const retorno = await resposta.json();
        if (retorno.ok && retorno.logado) definirJogador(retorno);
        else definirJogador(null);
    } catch (erro) {}
}

function atualizarEstatisticas(stats) {
    if (!stats) return;
    const campos = { statPartidas: stats.partidas, statJogadores: stats.jogadores, statMaior: stats.maior, statCampeao: stats.campeao };
    Object.entries(campos).forEach(([id, valor]) => {
        const el = document.getElementById(id);
        if (el) el.textContent = valor;
    });
}

async function entrarTelaCheia() {
    document.body.classList.add('jogo-expandido');
    btnTelaCheia.classList.add('btn-tela-ativa');
    btnTelaCheia.textContent = '↙ Tela normal';
    if (!document.fullscreenElement && painelJogo.requestFullscreen) {
        try { await painelJogo.requestFullscreen(); } catch (erro) {}
    }
}

async function sairTelaCheia() {
    document.body.classList.remove('jogo-expandido');
    btnTelaCheia.classList.remove('btn-tela-ativa');
    btnTelaCheia.textContent = '⛶ Tela cheia';
    if (document.fullscreenElement && document.exitFullscreen) {
        try { await document.exitFullscreen(); } catch (erro) {}
    }
}

function alternarTelaCheia() {
    if (document.body.classList.contains('jogo-expandido')) sairTelaCheia();
    else entrarTelaCheia();
}

function atualizarDicaFlutuante() {
    if (!dicaFlutuante) return;
    if (estado === 'jogando') {
        dicaFlutuante.classList.add('escondida');
        return;
    }
    dicaFlutuante.classList.remove('escondida');
    if (estado === 'pausado') dicaFlutuante.innerHTML = 'Pressione <strong>P</strong> para continuar';
    else if (estado === 'fim') dicaFlutuante.innerHTML = 'Pressione <strong>Espaço</strong> para jogar novamente';
    else dicaFlutuante.innerHTML = 'Pressione <strong>Espaço</strong> para começar';
}

function prepararTela() {
    atualizarDificuldadeTela();
    atualizarSkins(null);
    atualizarPlacarTela();
    atualizarDicaFlutuante();

    carregarSessao();

    if (abasAcesso) {
        abasAcesso.querySelectorAll('button').forEach(botao => {
            botao.addEventListener('click', () => {
                abasAcesso.querySelectorAll('button').forEach(b => b.classList.remove('ativo'));
                botao.classList.add('ativo');
                const cadastro = botao.dataset.aba === 'cadastro';
                formLoginJogador.classList.toggle('escondido', cadastro);
                formCadastroJogador.classList.toggle('escondido', !cadastro);
                mostrarMensagem(cadastro ? 'Crie um usuário e senha para salvar sua evolução.' : 'Entre para continuar seu progresso.', true);
            });
        });
    }

    formLoginJogador.addEventListener('submit', async evento => {
        evento.preventDefault();
        try {
            await enviarAcesso('login', usuarioLogin.value, senhaLogin.value);
            senhaLogin.value = '';
        } catch (erro) {
            mostrarMensagem(erro.message);
        }
    });

    formCadastroJogador.addEventListener('submit', async evento => {
        evento.preventDefault();
        try {
            await enviarAcesso('registrar', usuarioCadastro.value, senhaCadastro.value);
            usuarioLogin.value = usuarioCadastro.value;
            usuarioCadastro.value = '';
            senhaCadastro.value = '';
            formCadastroJogador.classList.add('escondido');
            formLoginJogador.classList.remove('escondido');
            abasAcesso.querySelectorAll('button').forEach(b => b.classList.toggle('ativo', b.dataset.aba === 'login'));
        } catch (erro) {
            mostrarMensagem(erro.message);
        }
    });

    if (btnSairJogador) {
        btnSairJogador.addEventListener('click', async () => {
            await fetch('php/ranking.php?acao=sair');
            definirJogador(null);
            mostrarMensagem('Você saiu da conta. Entre novamente para jogar.', true);
        });
    }

    document.querySelectorAll('.dificuldade-opcao').forEach(botao => {
        botao.addEventListener('click', () => {
            dificuldade = botao.dataset.dificuldade || 'normal';
            localStorage.setItem('flappy_marcio_dificuldade', dificuldade);
            atualizarDificuldadeTela();
            if (estado === 'inicio') desenhar();
        });
    });

    document.querySelectorAll('.filtros-ranking button').forEach(botao => {
        botao.addEventListener('click', () => {
            document.querySelectorAll('.filtros-ranking button').forEach(b => b.classList.remove('ativo'));
            botao.classList.add('ativo');
            filtroRanking = botao.dataset.rank || 'geral';
            carregarRanking();
        });
    });



    document.querySelectorAll('.skin-opcao').forEach(botao => {
        botao.addEventListener('click', () => {
            const escolhida = botao.dataset.skin || 'classica';
            if (!skinLiberada(escolhida, perfilAtual)) {
                mostrarMensagem('Essa skin ainda está bloqueada. Jogue mais para liberar.');
                return;
            }
            skinSelecionada = escolhida;
            localStorage.setItem('flappy_marcio_skin', skinSelecionada);
            atualizarSkins(perfilAtual);
            if (estado === 'inicio') desenhar();
        });
    });

    document.querySelectorAll('.menu-principal [data-ir]').forEach(botao => {
        botao.addEventListener('click', () => {
            const alvo = botao.dataset.ir;
            const mapa = { jogo: painelJogo, ranking: document.querySelector('.ranking-card'), perfil: document.getElementById('areaPerfil'), manual: document.getElementById('manualCard'), creditos: document.getElementById('areaCreditos') };
            if (alvo === 'manual' && manualCard.classList.contains('fechado')) btnManual.click();
            (mapa[alvo] || painelJogo).scrollIntoView({ behavior: 'smooth', block: 'start' });
        });
    });

    if (btnApresentacao) {
        btnApresentacao.addEventListener('click', () => {
            document.body.classList.toggle('modo-apresentacao');
            btnApresentacao.textContent = document.body.classList.contains('modo-apresentacao') ? 'Sair da apresentação' : 'Modo apresentação';
        });
    }

    btnTelaCheia.addEventListener('click', alternarTelaCheia);
    btnAtualizarRanking.addEventListener('click', carregarRanking);
    btnManual.addEventListener('click', () => {
        const fechado = manualCard.classList.toggle('fechado');
        manualIcone.textContent = fechado ? '+' : '−';
    });

    document.addEventListener('fullscreenchange', () => {
        if (!document.fullscreenElement && document.body.classList.contains('jogo-expandido')) {
            document.body.classList.remove('jogo-expandido');
            btnTelaCheia.classList.remove('btn-tela-ativa');
            btnTelaCheia.textContent = '⛶ Tela cheia';
        }
    });
}

function teclaEmCampo() {
    const tag = document.activeElement ? document.activeElement.tagName : '';
    return tag === 'INPUT' || tag === 'TEXTAREA';
}

document.addEventListener('keydown', evento => {
    if (evento.code === 'Space') {
        if (teclaEmCampo()) return;
        evento.preventDefault();
        if (estado === 'inicio' || estado === 'fim') tentarComecar();
        else if (estado === 'jogando') pular();
    }
    if (evento.code === 'KeyP' && !teclaEmCampo()) {
        evento.preventDefault();
        alternarPausa();
    }
});

canvas.addEventListener('mousedown', pular);
canvas.addEventListener('touchstart', evento => { evento.preventDefault(); pular(); }, { passive: false });

prepararTela();
carregarRanking();
loop();
