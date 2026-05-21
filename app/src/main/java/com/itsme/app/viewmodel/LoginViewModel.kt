package com.itsme.app.viewmodel
import android.app.Application
import androidx.lifecycle.*
import com.itsme.app.ItsmeApplication
import com.itsme.app.data.entities.User
import kotlinx.coroutines.launch

class LoginViewModel(app: Application) : AndroidViewModel(app) {
    private val db = (app as ItsmeApplication).database
    val allUsers = db.userDao().getAllUsers()
    private val _result = MutableLiveData<User?>()
    val loginResult: LiveData<User?> = _result

    fun login(u: String, p: String) = viewModelScope.launch { _result.value = db.userDao().login(u, p) }
    fun addUser(u: String, p: String, admin: Boolean) = viewModelScope.launch { db.userDao().insert(User(username=u, password=p, isAdmin=admin)) }
    fun deleteUser(user: User) = viewModelScope.launch { db.userDao().delete(user) }
    fun updateUser(user: User) = viewModelScope.launch { db.userDao().update(user) }
}
