window.openDatabaseMaintenanceModal=function(){console.log("openDatabaseMaintenanceModal called");const e=document.getElementById("databaseMaintenanceModal");if(!e){console.error("databaseMaintenanceModal element not found!"),window.showError?window.showError("Database Maintenance modal not found. Please refresh the page."):alert("Database Maintenance modal not found. Please refresh the page.");return}console.log("Opening database maintenance modal..."),console.log("Modal before changes:",e.style.display,e.classList.contains("hidden")),e.classList.remove("hidden"),e.style.display="flex",console.log("Modal after changes:",e.style.display,e.classList.contains("hidden")),console.log("Modal computed style:",window.getComputedStyle(e).display,window.getComputedStyle(e).visibility),console.log("Modal z-index:",window.getComputedStyle(e).zIndex),console.log("Modal position:",e.getBoundingClientRect()),document.getElementById("databaseMaintenanceLoading").style.display="none",switchDatabaseTab(document.querySelector('[data-tab="connection"]'),"connection"),loadCurrentDatabaseConfig()};function g(s){return s||(typeof window<"u"?window.event:void 0)}async function v(s){const a=g(s)?.target||document.querySelector('[data-action="scan-db"], #scanDatabaseConnectionsBtn'),t=document.getElementById("conversionResults");a&&(a.disabled=!0,a.textContent="🔄 Scanning..."),t&&(t.className="mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm",t.innerHTML="⏳ Scanning PHP files for database connections...",t.classList.remove("hidden"));try{const n=await(await fetch("/api/convert_to_centralized_db.php?action=scan&format=json&admin_token=whimsical_admin_2024")).json();if(n.success)n.needs_conversion>0?t&&(t.className="mt-3 px-3 py-2 bg-yellow-50 border border-yellow-200 rounded text-sm",t.innerHTML=`
                        <div class="font-medium text-yellow-800">⚠️ Files Need Conversion</div>
                        <div class="text-xs space-y-1 text-yellow-700">
                            <div>Total PHP files: ${n.total_files}</div>
                            <div>Files needing conversion: ${n.needs_conversion}</div>
                            <div class="">Files with direct PDO connections:</div>
                            <ul class="list-disc list-inside">
                                ${n.files.slice(0,10).map(i=>`<li>${i}</li>`).join("")}
                                ${n.files.length>10?`<li>... and ${n.files.length-10} more</li>`:""}
                            </ul>
                        </div>
                    `):t&&(t.className="mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm",t.innerHTML=`
                        <div class="font-medium text-green-800">✅ All Files Use Centralized Database!</div>
                        <div class="text-xs text-green-700">Scanned ${n.total_files} PHP files - no conversion needed</div>
                    `);else throw new Error(n.message||"Scan failed")}catch(o){t&&(t.className="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm",t.innerHTML=`<div class="text-red-800">❌ Scan failed: ${o.message}</div>`)}finally{a&&(a.disabled=!1,a.textContent="📊 Scan Files")}}async function w(s){const a=g(s)?.target||document.querySelector('[data-action="convert-db"], #convertDatabaseConnectionsBtn'),t=document.getElementById("conversionResults");if(confirm("This will modify files with direct PDO connections and create backups. Continue?")){a&&(a.disabled=!0,a.textContent="🔄 Converting..."),t&&(t.className="mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm",t.innerHTML="⏳ Converting files to use centralized database connections...",t.classList.remove("hidden"));try{const n=await(await fetch("/api/convert_to_centralized_db.php?action=convert&format=json&admin_token=whimsical_admin_2024")).json();if(n.success)n.converted>0?t&&(t.className="mt-3 px-3 py-2 bg-green-50 border border-green-200 rounded text-sm",t.innerHTML=`
                        <div class="font-medium text-green-800">🎉 Conversion Completed!</div>
                        <div class="text-xs space-y-1 text-green-700">
                            <div>Files converted: ${n.converted}</div>
                            <div>Conversion failures: ${n.failed}</div>
                            <div class="">💾 Backups were created for all modified files</div>
                            <div class="text-yellow-700">⚠️ Please test your application to ensure everything works correctly</div>
                        </div>
                        ${n.results.filter(i=>i.status==="converted").length>0?`
                            <details class="">
                                <summary class="cursor-pointer text-green-700 hover:text-green-900">View converted files</summary>
                                <ul class="list-disc list-inside text-xs">
                                    ${n.results.filter(i=>i.status==="converted").map(i=>`<li>${i.file} (${i.changes} changes)</li>`).join("")}
                                </ul>
                            </details>
                        `:""}
                    `):t&&(t.className="mt-3 px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm",t.innerHTML=`
                        <div class="font-medium text-blue-800">ℹ️ No Files Needed Conversion</div>
                        <div class="text-xs text-blue-700">All files are already using centralized database connections</div>
                    `);else throw new Error(n.message||"Conversion failed")}catch(o){t&&(t.className="mt-3 px-3 py-2 bg-red-50 border border-red-200 rounded text-sm",t.innerHTML=`<div class="text-red-800">❌ Conversion failed: ${o.message}</div>`)}finally{a&&(a.disabled=!1,a.textContent="🔄 Convert All")}}}function y(){window.open("/api/convert_to_centralized_db.php?admin_token=whimsical_admin_2024","_blank")}function h(){const s=document.getElementById("databaseBackupTablesContainer"),e=document.getElementById("databaseBackupToggleIcon");!s||!e||(s.classList.contains("hidden")?(s.classList.remove("hidden"),e.textContent="▼"):(s.classList.add("hidden"),e.textContent="▶"))}async function x(s){try{const e=document.getElementById("tableViewModal"),a=document.getElementById("tableViewTitle"),t=document.getElementById("tableViewContent");a&&(a.textContent=`Loading ${s}...`),t&&(t.innerHTML='<div class="text-center"><div class="inline-block animate-spin rounded-full h-8 w-8 border-b-2 border-blue-600"></div></div>'),e&&(e.style.display="flex");const n=await(await fetch("/api/db_manager.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({action:"query",sql:`SELECT * FROM \`${s}\` LIMIT 100`})})).json();if(n.success&&n.data){if(a&&(a.textContent=`Table: ${s} (${n.row_count} records shown, max 100)`),!Array.isArray(n.data)||n.data.length===0){t&&(t.innerHTML='<div class="text-center text-gray-500">Table is empty</div>');return}const i=Object.keys(n.data[0]),d=`
                <div class="overflow-x-auto max-h-96">
                    <table class="min-w-full bg-white border border-gray-200 text-xs">
                        <thead class="bg-gray-50 sticky top-0">
                            <tr>
                                ${i.map(r=>`<th class="border-b text-left font-semibold text-gray-700">${r}</th>`).join("")}
                            </tr>
                        </thead>
                        <tbody>
                            ${n.data.map(r=>`
                                <tr class="hover:bg-gray-50">
                                    ${i.map(L=>{let c=r[L];return c===null?c='<span class="text-gray-400">NULL</span>':typeof c=="string"&&c.length>50&&(c=c.substring(0,50)+"..."),`<td class="border-b">${c}</td>`}).join("")}
                                </tr>
                            `).join("")}
                        </tbody>
                    </table>
                </div>
            `;t&&(t.innerHTML=d)}else a&&(a.textContent=`Error loading ${s}`),t&&(t.innerHTML=`<div class="text-red-600">Error: ${n.error||"Failed to load table data"}</div>`)}catch(e){console.error("Error viewing table:",e);const a=document.getElementById("tableViewTitle"),t=document.getElementById("tableViewContent");a&&(a.textContent=`Error loading ${s}`),t&&(t.innerHTML=`<div class="text-red-600">Error: ${e.message}</div>`)}}function C(){const s=document.getElementById("tableViewModal");s&&(s.style.display="none")}async function D(){try{const e=await(await fetch("/api/get_database_info.php")).json();return e.success&&e.data&&e.data.total_active||"several"}catch{return"several"}}async function S(){const s=await D();if(!await(window.showConfirmationModal?window.showConfirmationModal({title:"Database Compact & Repair",subtitle:"Optimize and repair your database for better performance",message:"This operation will create a safety backup first, then optimize and repair all database tables to improve performance and fix any corruption issues.",details:`
            <ul>
                <li>✅ Create automatic safety backup before optimization</li>
                <li>🔧 Optimize ${s} database tables for better performance</li>
                <li>🛠️ Repair any table corruption or fragmentation issues</li>
                <li>⚡ Improve database speed and efficiency</li>
                <li>⏱️ Process typically takes 2-3 minutes</li>
            </ul>
        `,icon:"🔧",iconType:"info",confirmText:"Start Optimization",cancelText:"Cancel"}):Promise.resolve(confirm("Create a safety backup, then optimize and repair all database tables?"))))return;typeof window.showBackupProgressModal=="function"&&window.showBackupProgressModal("🔧 Database Compact & Repair","database-repair");const a=document.getElementById("backupProgressSteps"),t=document.getElementById("backupProgressTitle"),o=document.getElementById("backupProgressSubtitle");t&&(t.textContent="🔧 Database Compact & Repair"),o&&(o.textContent="Optimizing and repairing database tables...");try{a&&(a.innerHTML=`
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Creating safety backup...</p>
                        <p class="text-xs text-gray-500">Backing up database before optimization</p>
                    </div>
                </div>
            `);const i=await(await fetch("/api/backup_database.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({destination:"cloud"})})).json();if(!i.success)throw new Error("Failed to create safety backup: "+(i.error||"Unknown error"));a&&(a.innerHTML=`
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-blue-500 rounded-full flex items-center justify-center">
                            <div class="w-4 h-4 border-2 border-white border-t-transparent rounded-full animate-spin"></div>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Optimizing database tables...</p>
                        <p class="text-xs text-gray-500">Running OPTIMIZE and REPAIR operations</p>
                    </div>
                </div>
            `);const r=await(await fetch("/api/compact_repair_database.php",{method:"POST",headers:{"Content-Type":"application/x-www-form-urlencoded"},body:new URLSearchParams({})})).json();if(!r.success)throw new Error("Database optimization failed: "+(r.error||"Unknown error"));a&&(a.innerHTML=`
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Safety backup created</p>
                        <p class="text-xs text-gray-500">Database backed up successfully</p>
                    </div>
                </div>
                <div class="flex items-center space-x-3">
                    <div class="flex-shrink-0">
                        <div class="w-8 h-8 bg-green-500 rounded-full flex items-center justify-center">
                            <svg class="w-4 h-4 text-white" fill="currentColor" viewBox="0 0 20 20">
                                <path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"></path>
                            </svg>
                        </div>
                    </div>
                    <div class="flex-1">
                        <p class="text-sm font-medium text-gray-900">Database optimization complete</p>
                        <p class="text-xs text-gray-500">${r.tables_processed||0} tables optimized and repaired</p>
                    </div>
                </div>
            `),typeof window.showBackupCompletionDetails=="function"&&window.showBackupCompletionDetails({success:!0,filename:i.filename,filepath:i.filepath,size:i.size,timestamp:i.timestamp,destinations:["Server"],tables_optimized:r.tables_processed||0,operation_type:"Database Compact & Repair"})}catch(n){console.error("Database optimization error:",n),typeof window.showError=="function"?window.showError(n.message||"Database optimization failed"):alert(n.message||"Database optimization failed")}}function l(s,e,a){if(!s)return;const t="px-3 py-2 border rounded text-sm";e?s.className=`${t} bg-green-50 border-green-200`:s.className=`${t} bg-red-50 border-red-200`,s.innerHTML=a,s.classList.remove("hidden")}async function k(s){try{const e=document.getElementById("credentialsUpdateResult"),a=s?.target||document.activeElement,t={host:document.getElementById("newHost")?.value,database:document.getElementById("newDatabase")?.value,username:document.getElementById("newUsername")?.value,password:document.getElementById("newPassword")?.value,environment:document.getElementById("environmentSelect")?.value,ssl_enabled:document.getElementById("sslEnabled")?.checked||!1,ssl_cert:document.getElementById("sslCertPath")?.value||""};if(!t.host||!t.database||!t.username){l(e,!1,"Please fill in all required fields");return}const o=async()=>{a&&(a.disabled=!0,a.textContent="💾 Updating..."),e&&(e.className="px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm",e.innerHTML="⏳ Updating configuration...",e.classList.remove("hidden"));try{const i=await(await fetch("/api/database_maintenance.php?action=update_config&admin_token=whimsical_admin_2024",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(t)})).json();i.success?(l(e,!0,`
                        <div class="font-medium text-green-800">✅ Configuration Updated!</div>
                        <div class="text-xs text-green-700">Backup created: ${i.backup_created}</div>
                        <div class="text-xs text-yellow-700">⚠️ Please refresh the page to use new settings</div>
                    `),setTimeout(()=>{try{loadCurrentDatabaseConfig()}catch{}},2e3)):l(e,!1,`Update failed: ${i.message}`)}catch(n){l(e,!1,`Network error: ${n.message}`)}finally{a&&(a.disabled=!1,a.textContent="💾 Update Credentials")}};typeof window.showConfirmationModal=="function"?window.showConfirmationModal({title:"Update database credentials?",message:`A backup will be created automatically for ${t.environment} environment(s).`,confirmText:"Yes, Update",cancelText:"Cancel",onConfirm:o}):confirm(`Are you sure you want to update database credentials for ${t.environment} environment(s)? A backup will be created automatically.`)&&await o()}catch(e){console.error("[AdminSettings] updateDatabaseConfig error",e)}}async function T(s){try{const e=document.getElementById("sslTestResult"),a=s?.target||document.activeElement,t={host:document.getElementById("testHost")?.value||document.getElementById("newHost")?.value,database:document.getElementById("testDatabase")?.value||document.getElementById("newDatabase")?.value,username:document.getElementById("testUsername")?.value||document.getElementById("newUsername")?.value,password:document.getElementById("testPassword")?.value||document.getElementById("newPassword")?.value,ssl_enabled:!0,ssl_cert:document.getElementById("sslCertPath")?.value};if(!t.ssl_cert){l(e,!1,"Please specify SSL certificate path");return}a&&(a.disabled=!0,a.textContent="🔄 Testing SSL..."),e&&(e.className="px-3 py-2 bg-blue-50 border border-blue-200 rounded text-sm",e.innerHTML="⏳ Testing SSL connection...",e.classList.remove("hidden"));try{const n=await(await fetch("/api/database_maintenance.php?action=test_connection&admin_token=whimsical_admin_2024",{method:"POST",headers:{"Content-Type":"application/json"},body:JSON.stringify(t)})).json();n.success?l(e,!0,`
                    <div class="font-medium text-green-800">🔒 SSL Connection Successful!</div>
                    <div class="text-xs space-y-1 text-green-700">
                        <div>SSL Certificate: Valid</div>
                        <div>Encryption: Active</div>
                        <div>MySQL Version: ${n.info?.mysql_version||""}</div>
                    </div>
                `):l(e,!1,`SSL connection failed: ${n.message}`)}catch(o){l(e,!1,`SSL test error: ${o.message}`)}finally{a&&(a.disabled=!1,a.textContent="🔒 Test SSL Connection")}}catch(e){console.error("[AdminSettings] testSSLConnection error",e)}}typeof window<"u"&&(window.scanDatabaseConnections=v,window.convertDatabaseConnections=w,window.openConversionTool=y,window.toggleDatabaseBackupTables=h,window.viewTable=x,window.closeTableViewModal=C,window.compactRepairDatabase=S,window.updateDatabaseConfig=k,window.testSSLConnection=T);let f=!1;function u(s=document){try{const e=[{contains:"scanDatabaseConnections",action:"scan-db"},{contains:"convertDatabaseConnections",action:"convert-db"},{contains:"openConversionTool",action:"open-conversion-tool"},{contains:"compactRepairDatabase",action:"compact-repair"},{contains:"toggleDatabaseBackupTables",action:"toggle-backup-tables"},{contains:"closeTableViewModal",action:"close-table-view"},{contains:"updateDatabaseConfig",action:"update-db-config"},{contains:"testSSLConnection",action:"test-ssl"}];s.querySelectorAll("[onclick]").forEach(t=>{const o=(t.getAttribute("onclick")||"").toString();for(const n of e)o.includes(n.contains)&&(t.dataset.action||(t.dataset.action=n.action));if(o.includes("viewTable(")){t.dataset.action||(t.dataset.action="view-table");try{const n=o.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/),i=(n&&(n[1]||n[2]||n[3]||"")).toString().trim().replace(/^`|`$/g,"").replace(/^\"|\"$/g,"").replace(/^'|'$/g,"");i&&!t.dataset.table&&(t.dataset.table=i)}catch{}}})}catch(e){console.debug("[AdminSettings] tagInlineHandlersForMigration error",e)}}function m(s=document){try{const e=['[onclick*="scanDatabaseConnections"]','[onclick*="convertDatabaseConnections"]','[onclick*="openConversionTool"]','[onclick*="compactRepairDatabase"]','[onclick*="toggleDatabaseBackupTables"]','[onclick*="closeTableViewModal"]','[onclick*="viewTable("]','[onclick*="updateDatabaseConfig"]','[onclick*="testSSLConnection"]'];s.querySelectorAll(e.join(",")).forEach(a=>{a.dataset.onclickLegacy||(a.dataset.onclickLegacy=a.getAttribute("onclick")||""),a.removeAttribute("onclick"),a.dataset.migrated="true"})}catch(e){console.debug("[AdminSettings] stripInlineHandlersForMigration error",e)}}function p(){if(f)return;f=!0;const s=()=>{u(),m()};document.readyState!=="loading"?s():document.addEventListener("DOMContentLoaded",()=>s(),{once:!0}),b();try{new MutationObserver(a=>{for(const t of a)t.type==="childList"?t.addedNodes.forEach(o=>{o.nodeType===1&&(u(o),m(o),b(o))}):t.type==="attributes"&&t.attributeName==="onclick"&&(u(t.target),m(t.target))}).observe(document.documentElement,{childList:!0,subtree:!0,attributes:!0,attributeFilter:["onclick"]})}catch(e){console.debug("[AdminSettings] MutationObserver unavailable",e)}document.addEventListener("change",e=>{const a=e.target;if(a&&a.matches&&a.matches("#sslEnabled")){const t=document.getElementById("sslOptions");t&&(a.checked?t.classList.remove("hidden"):t.classList.add("hidden"))}},!0),document.addEventListener("click",e=>{const a=e.target,t=n=>a.closest(n);if(t('[data-action="scan-db"]')){e.preventDefault(),v(e);return}if(t('[data-action="convert-db"]')){e.preventDefault(),w(e);return}if(t('[data-action="open-conversion-tool"]')){e.preventDefault(),y();return}if(t('[data-action="compact-repair"]')){e.preventDefault(),S();return}if(t('[data-action="toggle-backup-tables"]')){e.preventDefault(),h();return}if(t('[data-action="close-table-view"]')){e.preventDefault(),C();return}const o=t('[data-action="view-table"]');if(o){e.preventDefault();let n=o.dataset.table||o.dataset.tableName;if(!n&&o.dataset.onclickLegacy)try{const i=o.dataset.onclickLegacy.match(/viewTable\((?:'([^']+)'|\"([^\"]+)\"|([^\)]+))\)/);n=(i&&(i[1]||i[2]||i[3]||"")).toString().trim()}catch{}n?x(n):console.warn("[AdminSettings] view-table clicked but no table name found");return}if(t('[data-action="update-db-config"]')){e.preventDefault(),k(e);return}if(t('[data-action="test-ssl"]')){e.preventDefault(),T(e);return}},!0)}typeof window<"u"&&(document.readyState!=="loading"?p():document.addEventListener("DOMContentLoaded",()=>p(),{once:!0}));function b(s=document){try{const e=s.querySelector?s.querySelector("#sslEnabled"):null,a=s.querySelector?s.querySelector("#sslOptions"):null;e&&a&&a.classList.toggle("hidden",!e.checked)}catch{}}
