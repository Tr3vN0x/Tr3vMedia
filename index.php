// app.js

// ----------------------------------------------------
// 0. GLOBAL VARS 
// ----------------------------------------------------
let auth;
let db;
let currentUser = null;
let currentProfileData = {};
let allUsersCache = [];
let requestCache = {};
let currentChatId = null;
let chatListener = null;
let currentChatPartnerId = null; 

const DEFAULT_AVATAR = "https://cdn-icons-png.flaticon.com/512/149/149071.png";

const firebaseConfig = {
    apiKey: "AIzaSyDVHxatohLvJNqIHXjf1ZXdmmWX5W1EpNw",
    authDomain: "duoup-cfae6.firebaseapp.com",
    projectId: "duoup-cfae6",
    messagingSenderId: "812263060524",
    appId: "1:812263060524:web:ac09c3ae610db1cd110d89",
    measurementId: "G-46B67F90FK"
};

const CO_FOUNDER_UIDS = ["GmH4xJp92vY8sbwIWgJWjFw9MOQ2"];
const DEV_67_UIDS = ["hmXTc2hkF5Vvqrn8xuNjK3S9ybS2", "kHsYFJVHCGfQzI16nJH0mg1rv7u1"];
const CUSTOM_BADGE_MAP = {
    "kHsYFJVHCGfQzI16nJH0mg1rv7u1": { title: "Pilex6", color: "pilex6" }, 
};

// ----------------------------------------------------
// 1. INITIALIZATION & AUTHENTICATION
// ----------------------------------------------------
function initFirebase() {
    if (!firebase.apps.length) firebase.initializeApp(firebaseConfig);
    auth = firebase.auth();
    db = firebase.firestore();

    auth.onAuthStateChanged(user => {
        currentUser = user;
        document.getElementById('loadingOverlay').classList.add('hidden');

        if (currentUser) {
            document.getElementById('loginPage').classList.add('hidden');
            document.getElementById('dashboard').classList.remove('hidden');
            showTab('allUsers');
            checkAndCreateUserProfile();
        } else {
            document.getElementById('loginPage').classList.remove('hidden');
            document.getElementById('dashboard').classList.add('hidden');
        }
    });
}

// ----------------------------------------------------
// 2. BADGE FUNCTIONS
// ----------------------------------------------------
function get67Badge(uid) {
    if (!DEV_67_UIDS.includes(uid)) return '';
    return `<span class="badge-67">67</span>`;
}

function getCoFounderBadge(uid) {
    if (CO_FOUNDER_UIDS.includes(uid)) {
        const style = `
            background: linear-gradient(90deg, #00ffff, #ff00ff); 
            color: #111; 
            font-size: 0.75em; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 8px; 
            box-shadow: 0 0 8px #00ffff;
        `;
        return `<div style="${style}">⚡ DUO UP DEV</div>`;
    }
    return '';
}

function getCustomBadge(uid) {
    const customData = CUSTOM_BADGE_MAP[uid];
    if (customData) {
        const style = `
            background: linear-gradient(45deg, #00ff00, #009900); 
            color: #0b0b0b; 
            font-size: 0.75em; 
            padding: 2px 8px; 
            border-radius: 4px; 
            font-weight: bold;
            text-transform: uppercase;
            margin-left: 8px;
            box-shadow: 0 0 8px #00ff00;
        `;
        return `<div style="${style}">${customData.title}</div>`;
    }
    return '';
}

// ----------------------------------------------------
// 3. AUTH FUNCTIONS
// ----------------------------------------------------
function login(){ auth.signInWithPopup(new firebase.auth.GoogleAuthProvider()); }
function logout(){ 
    if(chatListener) chatListener(); 
    auth.signOut(); 
}

// ----------------------------------------------------
// 4. TAB MANAGEMENT
// ----------------------------------------------------
function showTab(tab){
    document.querySelectorAll('#allUsersTab,#searchTab,#friendsTab,#messagingTab,#profileTab,#viewProfileTab').forEach(e=>e.classList.add('hidden'));
    if(tab!=='messaging' && chatListener){ chatListener(); chatListener=null; currentChatId=null; currentChatPartnerId=null; document.getElementById('messageDisplay').innerHTML=''; document.getElementById('chatHeader').textContent='Select a Duo to start messaging'; document.getElementById('messageInputArea').classList.add('hidden'); }
    document.querySelectorAll('#navigationBar button').forEach(btn => btn.classList.remove('active'));
    document.getElementById('tab-'+tab)?.classList.add('active');
    switch(tab){
        case 'allUsers': loadAllUsers(); break;
        case 'search': filterSearchResults(); break;
        case 'friends': loadFriends(); break;
        case 'messaging': loadChatList(); break;
        case 'profile': loadUserProfile(); toggleLfdCategory(); break;
        default: break;
    }
    document.getElementById(tab+'Tab')?.classList.remove('hidden'); 
}

// ----------------------------------------------------
// 5. LOAD USERS
// ----------------------------------------------------
async function loadAllUsers(){
    if(!currentUser) return;
    const container=document.getElementById('allUsersTab');
    container.innerHTML="<p style='width:100%; text-align:center;'>Loading Suggested Players...</p>";

    try{
        const snapshot=await db.collection('users').get();
        allUsersCache=snapshot.docs.map(d=>({id:d.id,...d.data()}));
        const requestsSnap = await db.collection('friendRequests').where('participants','array-contains',currentUser.uid).get();
        requestCache = {};
        requestsSnap.forEach(doc=>{
            const data=doc.data();
            const otherUid=data.participants.find(uid=>uid!==currentUser.uid);
            if(otherUid) requestCache[otherUid]={id:doc.id,status:data.status,senderId:data.senderId,receiverId:data.receiverId};
        });
        renderAllUsers();
    }catch(e){
        container.innerHTML="<p style='color:red;text-align:center;'>Failed to load users.</p>";
        console.error(e);
    }
}

function renderAllUsers(){
    const container = document.getElementById('allUsersTab');
    container.innerHTML='';
    allUsersCache.forEach(user=>{
        const badgeHTML = getCoFounderBadge(user.id) + getCustomBadge(user.id) + get67Badge(user.id);
        const card = document.createElement('div');
        card.className='card';
        card.innerHTML=`<div class="card-content-box"><div class="nickname-group"><strong>${user.nickname || 'Unknown'}</strong>${badgeHTML}</div></div>`;
        container.appendChild(card);
    });
}

// ----------------------------------------------------
// 6. INIT
// ----------------------------------------------------
window.onload = () => initFirebase();
