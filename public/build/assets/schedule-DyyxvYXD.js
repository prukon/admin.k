document.addEventListener("DOMContentLoaded",function(){var u=document.getElementById("settingsModal"),m=new bootstrap.Modal(u),f=document.getElementById("createStatusModal"),h=new bootstrap.Modal(f),E=document.getElementById("editStatusModal"),g=new bootstrap.Modal(E),v=document.getElementById("btn-settings"),n=document.querySelector("#statuses-table tbody"),y=document.getElementById("btn-new-status");v.addEventListener("click",function(){S(),m.show()}),y.addEventListener("click",function(){document.getElementById("createStatusForm").reset(),document.querySelectorAll("#createIconList .icon-item").forEach(t=>t.classList.remove("selected")),document.getElementById("createIcon").value="",h.show()});function S(){fetch("{{ url('admin/statuses')  }}").then(t=>t.json()).then(t=>{n.innerHTML="",t.statuses.forEach(e=>{let o=document.createElement("tr");o.innerHTML=`
                        <td>
                            ${e.name}
                            ${e.is_system?`<i class="fas fa-question-circle ms-1"
                                      data-bs-toggle="tooltip"
                                      title="Системный статус. Невозможно удалить"
                                   ></i>`:""}
                        </td>
                        <td>
                            ${e.icon?`<i class="${e.icon}"
                                     style="background-color: ${e.color};
                                            color: #000000;
                                            padding: 5px;
                                            border-radius: 3px;"></i>`:""}
                        </td>
                        <td>
                            ${e.is_system?"":`<button class="btn btn-sm btn-success"
                                           data-action="edit"
                                           data-id="${e.id}"
                                           data-name="${e.name}"
                                           data-icon="${e.icon??""}"
                                           data-color="${e.color??""}">
                                       Изменить
                                   </button>
                                   <button class="btn btn-sm btn-danger"
                                           data-action="delete"
                                           data-id="${e.id}">
                                       Удалить
                                   </button>`}
                        </td>
                    `,n.appendChild(o)});var a=[].slice.call(document.querySelectorAll('[data-bs-toggle="tooltip"]'));a.map(function(e){return new bootstrap.Tooltip(e)})}).catch(t=>console.error(t))}var d=document.getElementById("createStatusForm");d.addEventListener("submit",function(t){t.preventDefault();let a=new FormData(d);fetch("{{ route('statuses.store') }}",{method:"POST",headers:{"X-CSRF-TOKEN":"{{ csrf_token() }}"},body:a}).then(e=>e.json()).then(e=>{e.success?showSuccessModal("Создание статуса","Статус успешно создан.",1):$("#errorModal").modal("show")}).catch(e=>console.error(e))});var c=document.getElementById("editStatusForm");n.addEventListener("click",function(t){let a=t.target.dataset.action;if(a==="edit"){let e=t.target.dataset.id,o=t.target.dataset.name,i=t.target.dataset.icon,I=t.target.dataset.color||"#ffffff";document.getElementById("editStatusId").value=e,document.getElementById("editName").value=o,document.getElementById("editIcon").value=i,document.getElementById("editColor").value=I,document.querySelectorAll("#editIconList .icon-item").forEach(s=>{s.classList.remove("selected"),s.dataset.icon===i&&s.classList.add("selected")}),g.show()}else a==="delete"&&showConfirmDeleteModal("Удаление статуса","Вы уверены, что хотите удалить этот статус? (Ранее установленные значения для дней с этим статусом останутся без изменений.)",function(){let e=t.target.dataset.id;fetch("{{ url('admin/statuses') }}/"+e,{method:"DELETE",headers:{"X-CSRF-TOKEN":"{{ csrf_token() }}"}}).then(o=>o.json()).then(o=>{o.success?showSuccessModal("Удаление статуса","Статус успешно удален.",1):$("#errorModal").modal("show")}).catch(o=>console.error(o))})}),c.addEventListener("submit",function(t){t.preventDefault();let a=document.getElementById("editStatusId").value,e=new FormData(c);e.append("_method","PATCH"),fetch("{{ url('admin/statuses') }}/"+a,{method:"POST",headers:{"X-CSRF-TOKEN":"{{ csrf_token() }}"},body:e}).then(o=>o.json()).then(o=>{o.success?showSuccessModal("Редактирование статуса","Статус успешно обновлен.",1):alert(o.error??"Ошибка при обновлении статуса")}).catch(o=>console.error(o))});let l=document.getElementById("createIconList");l.querySelectorAll(".icon-item").forEach(t=>{t.addEventListener("click",function(){l.querySelectorAll(".icon-item").forEach(a=>a.classList.remove("selected")),this.classList.add("selected"),document.getElementById("createIcon").value=this.dataset.icon})});let r=document.getElementById("editIconList");r.querySelectorAll(".icon-item").forEach(t=>{t.addEventListener("click",function(){r.querySelectorAll(".icon-item").forEach(a=>a.classList.remove("selected")),this.classList.add("selected"),document.getElementById("editIcon").value=this.dataset.icon})})});
