/* ============================
   ✅ SYSTEM PANEL JS
   system.js
============================ */

let charts = {};

function loadData(includeAccordion = false){
    $.getJSON('system-panel.php?ajax=1', d => {
        $('#widgets').html('');

        widget('System', d.system);
        widget('PHP', d.php);
        widget('Server', d.server);
        if(d.resources) bars(d.resources);
        widget('Rusage', d.rusage);
        widget('Geo', d.geo);

        spark('Disk Spark', d.spark.disk, 'disk');
        spark('RAM Spark', d.spark.ram, 'ram');

        if(includeAccordion){
            renderAccordionSidebar(d);
        }
    });
}

function widget(title, obj){
    let h=`<div class='col-md-6'><div class='card'><div class='card-body'><h6>${title}</h6>`;
    for(let k in obj) h+=`<div class='d-flex justify-content-between'><small>${k}</small><small>${obj[k]}</small></div>`;
    h+='</div></div></div>';
    $('#widgets').append(h);
}

function bars(r){
    $('#widgets').append(`
    <div class='col-md-6'><div class='card'><div class='card-body'>
    <h6>Resources</h6>
    Disk
    <div class='progress mb-2'><div class='progress-bar' style='width:${r.disk}%'></div></div>
    ${r.ram!==null?`RAM<div class='progress'><div class='progress-bar bg-success' style='width:${r.ram}%'></div></div>`:''}
    </div></div></div>`);
}

function spark(title,data,id){
    $('#widgets').append(`<div class='col-md-6'><div class='card'><div class='card-body'><h6>${title}</h6><canvas id='${id}' height='60'></canvas></div></div></div>`);
    new Chart(document.getElementById(id),{
        type:'line',
        data:{labels:data.map((_,i)=>i),datasets:[{data,borderWidth:1,pointRadius:0}]},
        options:{plugins:{legend:false},scales:{x:{display:false},y:{display:false}}}
    });
}

function renderAccordionSidebar(d){
    let html = `
    <div class="accordion" id="sysAccordion">

        <!-- Extensions -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#ext">
                    <i class="fa-solid fa-puzzle-piece me-2"></i>
                    <span lang="php_extensions"></span> (${d.extensions.length})
                </button>
            </h2>
            <div id="ext" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${d.extensions.map(e =>
                        `<span class="badge bg-info text-dark me-1 mb-1">${e}</span>`
                    ).join('')}
                </div>
            </div>
        </div>

        <!-- Database -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#db">
                    <i class="fa-solid fa-database me-2"></i>
                    <span lang="databases"></span>
                </button>
            </h2>
            <div id="db" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${Object.entries(d.database).map(([k,v]) =>
                        `<div class="d-flex justify-content-between">
                            <span>${k}</span>
                            <span class="badge ${v==='Available'?'bg-success':'bg-danger'}">${v}</span>
                        </div>`
                    ).join('')}
                </div>
            </div>
        </div>

        <!-- Apache Modules -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#apache">
                    <i class="fa-solid fa-cubes me-2"></i>
                    <span lang="apache_modules"></span> (${d.apache_modules.length})
                </button>
            </h2>
            <div id="apache" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${d.apache_modules.map(m =>
                        `<span class="badge bg-secondary me-1 mb-1">${m}</span>`
                    ).join('')}
                </div>
            </div>
        </div>

        <!-- Logs -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text-warning"
                    data-bs-toggle="collapse" data-bs-target="#logs">
                    <i class="fa-solid fa-file-lines me-2"></i>
                    <span lang="php_logs"></span>
                </button>
            </h2>
            <div id="logs" class="accordion-collapse collapse">
                <div class="accordion-body">
                    <pre class="small text-light">${d.logs.php}</pre>
                </div>
            </div>
        </div>

        <!-- Visitors -->
        <div class="accordion-item ">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#visitors">
                    <i class="fa-solid fa-users me-2"></i>
                    <span lang="active_visitors"></span> (${d.visitors.length})
                </button>
            </h2>
            <div id="visitors" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${d.visitors.map(v =>
                        `<div class="d-flex justify-content-between">
                            <span>${v.ip}</span>
                            <span>${v.time}</span>
                        </div>`
                    ).join('')}
                </div>
            </div>
        </div>

        <!-- File Permissions -->
        <div class="accordion-item ">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#perms">
                    <i class="fa-solid fa-lock me-2"></i>
                    <span lang="file_permissions"></span>
                </button>
            </h2>
            <div id="perms" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${Object.entries(d.permissions).map(([file,perm]) =>
                        `<div class="d-flex justify-content-between">
                            <span>${file}</span>
                            <span class="badge ${perm==='644'||perm==='600'?'bg-success':'bg-danger'}">${perm}</span>
                        </div>`
                    ).join('')}
                </div>
            </div>
        </div>

        <!-- PHP.ini Settings -->
        <div class="accordion-item">
            <h2 class="accordion-header">
                <button class="accordion-button collapsed text"
                    data-bs-toggle="collapse" data-bs-target="#phpini">
                    <i class="fa-solid fa-gear me-2"></i>
                    <span lang="php_ini_settings"></span>
                </button>
            </h2>
            <div id="phpini" class="accordion-collapse collapse">
                <div class="accordion-body">
                    ${Object.entries(d.php_ini).map(([k,v]) =>
                        `<div class="d-flex justify-content-between">
                            <span>${k}</span>
                            <span>${v}</span>
                        </div>`
                    ).join('')}
                </div>
            </div>
        </div>

    </div>`;
    $('#sysSidebar').html(html);
}


document.addEventListener('DOMContentLoaded', ()=>{
    loadData(true); // ilk yüklemede accordion da çizilsin
    setInterval(()=>loadData(false), 5000); // sadece widgetlar yenilensin
});
